<?php

use App\Data\AiProviders\AiChatMessage;
use App\Data\AiProviders\AiReply;
use App\Enums\AiMessageRole;
use App\Enums\AiProvider;
use App\Exceptions\AiProviders\AiKeyRejectedException;
use App\Exceptions\AiProviders\AiOutOfCreditException;
use App\Exceptions\AiProviders\AiProviderUnavailableException;
use App\Exceptions\AiProviders\AiRateLimitedException;
use App\Models\AiConnection;
use App\Services\AiProviders\OpenAiCompatibleProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('sends OpenAI-compatible request shape for each preset and Custom with system message first', function (string $providerValue, ?string $customBaseUrl, string $expectedUrl): void {
    $secretKey = 'sk-distinctive-openai-compat-secret-9182';

    Http::fake([
        $expectedUrl => Http::response([
            'id' => 'chatcmpl-123',
            'model' => 'answered-model-v1',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Answer grounded in project data.',
                    ],
                ],
            ],
        ]),
    ]);

    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::from($providerValue),
        'base_url' => $customBaseUrl,
        'model' => 'requested-model',
        'api_key' => $secretKey,
    ]);

    $reply = app(OpenAiCompatibleProvider::class)->chat($connection, [
        new AiChatMessage(AiMessageRole::User, 'What is the status?'),
        new AiChatMessage(AiMessageRole::System, 'Answer only from context.'),
        new AiChatMessage(AiMessageRole::Assistant, 'Previous answer.'),
    ]);

    expect($reply)->toBeInstanceOf(AiReply::class)
        ->and($reply->text)->toBe('Answer grounded in project data.')
        ->and($reply->model)->toBe('answered-model-v1');

    Http::assertSent(function (Request $request) use ($expectedUrl, $secretKey): bool {
        $payload = $request->data();

        return $request->method() === 'POST'
            && $request->url() === $expectedUrl
            && $request->hasHeader('Authorization', "Bearer {$secretKey}")
            && ($payload['model'] ?? null) === 'requested-model'
            && ($payload['messages'][0]['role'] ?? null) === 'system'
            && ($payload['messages'][0]['content'] ?? null) === 'Answer only from context.'
            && ($payload['messages'][1]['role'] ?? null) === 'user'
            && ($payload['messages'][2]['role'] ?? null) === 'assistant';
    });
})->with([
    'openai' => ['openai', null, 'https://api.openai.com/v1/chat/completions'],
    'gemini' => ['gemini', null, 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions'],
    'deepseek' => ['deepseek', null, 'https://api.deepseek.com/v1/chat/completions'],
    'opencode' => ['opencode', null, 'https://opencode.ai/zen/v1/chat/completions'],
    'custom' => ['custom', 'https://llm.internal.example/v1', 'https://llm.internal.example/v1/chat/completions'],
]);

it('sends only :free models for OpenRouter when allow_paid_models is false, prioritizing a selected :free model and never sending a paid model', function (): void {
    config()->set('ai.providers.openrouter.models.free', [
        'meta-llama/llama-3.3-70b-instruct:free',
        'deepseek/deepseek-r1:free',
    ]);
    config()->set('ai.providers.openrouter.models.paid', [
        'anthropic/claude-sonnet-5-5',
        'openai/gpt-5-mini',
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'model' => 'deepseek/deepseek-r1:free',
            'choices' => [
                ['message' => ['content' => 'Free tier answer']],
            ],
        ]),
    ]);

    // Case 1: Selected model is :free -> placed first in models, no paid models.
    $freeConnection = AiConnection::factory()->openRouter()->make([
        'model' => 'qwen/qwen3-coder:free',
        'allow_paid_models' => false,
        'api_key' => 'sk-or-v1-free-secret-1234',
    ]);

    $reply = app(OpenAiCompatibleProvider::class)->chat($freeConnection, [
        new AiChatMessage(AiMessageRole::System, 'System instructions.'),
        new AiChatMessage(AiMessageRole::User, 'Hello?'),
    ]);

    expect($reply->text)->toBe('Free tier answer')
        ->and($reply->model)->toBe('deepseek/deepseek-r1:free');

    Http::assertSent(function (Request $request): bool {
        $payload = $request->data();
        $models = $payload['models'] ?? [];

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && ! array_key_exists('model', $payload)
            && $request->hasHeader('X-OpenRouter-Title', config('app.name', 'FunWork'))
            && $models === [
                'qwen/qwen3-coder:free',
                'meta-llama/llama-3.3-70b-instruct:free',
                'deepseek/deepseek-r1:free',
            ]
            && ! $request->hasHeader('X-User-Email');
    });

    // Case 2: Even if a connection somehow holds a paid model string while switch is off, never send any paid model.
    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'model' => 'meta-llama/llama-3.3-70b-instruct:free',
            'choices' => [
                ['message' => ['content' => 'Still free']],
            ],
        ]),
    ]);

    $guardedConnection = AiConnection::factory()->openRouter()->make([
        'model' => 'openai/gpt-5',
        'allow_paid_models' => false,
        'api_key' => 'sk-or-v1-free-secret-1234',
    ]);

    app(OpenAiCompatibleProvider::class)->chat($guardedConnection, [
        new AiChatMessage(AiMessageRole::User, 'Hello?'),
    ]);

    Http::assertSent(function (Request $request): bool {
        $models = $request->data()['models'] ?? [];

        return $models === [
            'meta-llama/llama-3.3-70b-instruct:free',
            'deepseek/deepseek-r1:free',
        ];
    });
});

it('appends configured paid fallback models for OpenRouter only when allow_paid_models is true', function (): void {
    config()->set('ai.providers.openrouter.models.free', [
        'meta-llama/llama-3.3-70b-instruct:free',
        'deepseek/deepseek-r1:free',
    ]);
    config()->set('ai.providers.openrouter.models.paid', [
        'anthropic/claude-sonnet-5-5',
        'openai/gpt-5-mini',
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/chat/completions' => Http::response([
            'model' => 'anthropic/claude-sonnet-5-5',
            'choices' => [
                ['message' => ['content' => 'Paid fallback answer']],
            ],
        ]),
    ]);

    $connection = AiConnection::factory()->openRouter()->make([
        'model' => 'deepseek/deepseek-r1:free',
        'allow_paid_models' => true,
        'api_key' => 'sk-or-v1-paid-secret-5678',
    ]);

    $reply = app(OpenAiCompatibleProvider::class)->chat($connection, [
        new AiChatMessage(AiMessageRole::User, 'Hello?'),
    ]);

    expect($reply->model)->toBe('anthropic/claude-sonnet-5-5');

    Http::assertSent(function (Request $request): bool {
        $models = $request->data()['models'] ?? [];

        return $models === [
            'deepseek/deepseek-r1:free',
            'meta-llama/llama-3.3-70b-instruct:free',
            'anthropic/claude-sonnet-5-5',
            'openai/gpt-5-mini',
        ];
    });
});

it('maps OpenAI-compatible HTTP status errors to S4 named exceptions without leaking the API key', function (
    string $providerValue,
    bool $allowPaidModels,
    int $status,
    array $body,
    string $expectedException,
    string $expectedMessage,
): void {
    $secretKey = 'sk-leak-sentinel-openai-9999-xyz';

    Http::fake([
        '*' => Http::response([
            ...$body,
            'echoed_secret' => $secretKey,
        ], $status),
    ]);

    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::from($providerValue),
        'allow_paid_models' => $allowPaidModels,
        'api_key' => $secretKey,
    ]);

    try {
        app(OpenAiCompatibleProvider::class)->chat($connection, [
            new AiChatMessage(AiMessageRole::User, 'Hi'),
        ]);
        $this->fail("Expected {$expectedException} to be thrown.");
    } catch (Throwable $exception) {
        expect($exception)->toBeInstanceOf($expectedException)
            ->and($exception->getMessage())->toBe($expectedMessage)
            ->and($exception->getMessage())->not->toContain($secretKey);
    }
})->with([
    '401 key rejected' => [
        'openai',
        false,
        401,
        ['error' => ['message' => 'Invalid API key']],
        AiKeyRejectedException::class,
        'Your OpenAI key was rejected. Update it in Settings → Integrations.',
    ],
    '403 key rejected' => [
        'deepseek',
        false,
        403,
        ['error' => ['message' => 'Forbidden']],
        AiKeyRejectedException::class,
        'Your DeepSeek key was rejected. Update it in Settings → Integrations.',
    ],
    '402 out of credit' => [
        'openrouter',
        true,
        402,
        ['error' => ['message' => 'Insufficient credits']],
        AiOutOfCreditException::class,
        'Your OpenRouter account has no credit left.',
    ],
    '429 insufficient_quota billing error' => [
        'openai',
        false,
        429,
        ['error' => ['code' => 'insufficient_quota', 'type' => 'insufficient_quota', 'message' => 'You exceeded your current quota']],
        AiOutOfCreditException::class,
        'Your OpenAI account has no credit left.',
    ],
    '429 rate limited' => [
        'openai',
        false,
        429,
        ['error' => ['code' => 'rate_limit_exceeded', 'message' => 'Rate limit reached']],
        AiRateLimitedException::class,
        "You reached OpenAI's usage limit. Try again later.",
    ],
    '429 openrouter free daily limit' => [
        'openrouter',
        false,
        429,
        ['error' => ['message' => 'Free limit reached']],
        AiRateLimitedException::class,
        "You reached OpenRouter's usage limit. Try again later. It resets at midnight UTC.",
    ],
    '429 openrouter paid switch on' => [
        'openrouter',
        true,
        429,
        ['error' => ['message' => 'Rate limit reached']],
        AiRateLimitedException::class,
        "You reached OpenRouter's usage limit. Try again later.",
    ],
    '500 unavailable' => [
        'gemini',
        false,
        500,
        ['error' => ['message' => 'Internal server error']],
        AiProviderUnavailableException::class,
        'Google Gemini is not responding. Try again in a moment.',
    ],
    '503 unavailable' => [
        'opencode',
        false,
        503,
        ['error' => ['message' => 'Service unavailable']],
        AiProviderUnavailableException::class,
        'OpenCode is not responding. Try again in a moment.',
    ],
]);

it('maps connection exceptions and timeouts to AiProviderUnavailableException without leaking the API key', function (): void {
    $secretKey = 'sk-leak-sentinel-timeout-7777';

    Http::fake(function () use ($secretKey): never {
        throw new ConnectionException("cURL error 28: Operation timed out with key {$secretKey}");
    });

    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::OpenAi,
        'api_key' => $secretKey,
    ]);

    try {
        app(OpenAiCompatibleProvider::class)->chat($connection, [
            new AiChatMessage(AiMessageRole::User, 'Hi'),
        ]);
        $this->fail('Expected AiProviderUnavailableException to be thrown.');
    } catch (AiProviderUnavailableException $exception) {
        expect($exception->getMessage())
            ->toBe('OpenAI is not responding. Try again in a moment.')
            ->not->toContain($secretKey);
    }
});

it('lets unexpected HTTP responses fail cleanly without leaking the key or raw response body', function (): void {
    $secretKey = 'sk-leak-sentinel-unexpected-404';
    $rawBodyMarker = 'RAW_PROVIDER_BODY_SENSITIVE_DETAIL';

    Http::fake([
        '*' => Http::response([
            'error' => ['message' => "{$rawBodyMarker} {$secretKey}"],
        ], 400),
    ]);

    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::OpenAi,
        'api_key' => $secretKey,
    ]);

    try {
        app(OpenAiCompatibleProvider::class)->chat($connection, [
            new AiChatMessage(AiMessageRole::User, 'Hi'),
        ]);
        $this->fail('Expected RuntimeException to be thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->not->toContain($secretKey)
            ->not->toContain($rawBodyMarker);
    }
});
