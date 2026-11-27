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
use App\Services\AiProviders\AnthropicProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('sends Anthropic native Messages API request shape and joins text content blocks', function (): void {
    $secretKey = 'sk-ant-distinctive-secret-8844';

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'id' => 'msg_01',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-5-5-20261001',
            'content' => [
                ['type' => 'thinking', 'thinking' => 'Internal reasoning that should be ignored.'],
                ['type' => 'text', 'text' => 'First block answer.'],
                ['type' => 'text', 'text' => 'Second block answer.'],
            ],
        ]),
    ]);

    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::Anthropic,
        'model' => 'claude-sonnet-5-5',
        'api_key' => $secretKey,
    ]);

    $reply = app(AnthropicProvider::class)->chat($connection, [
        new AiChatMessage(AiMessageRole::System, 'Ground every answer in project data.'),
        new AiChatMessage(AiMessageRole::User, 'What decisions exist?'),
        new AiChatMessage(AiMessageRole::Assistant, 'ADR-001 covers auth.'),
        new AiChatMessage(AiMessageRole::User, 'Summarize it.'),
    ]);

    expect($reply)->toBeInstanceOf(AiReply::class)
        ->and($reply->text)->toBe("First block answer.\nSecond block answer.")
        ->and($reply->model)->toBe('claude-sonnet-5-5-20261001');

    Http::assertSent(function (Request $request) use ($secretKey): bool {
        $payload = $request->data();
        $roles = array_column($payload['messages'] ?? [], 'role');

        return $request->method() === 'POST'
            && $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', $secretKey)
            && $request->hasHeader('anthropic-version', config('ai.providers.anthropic.version'))
            && ! $request->hasHeader('Authorization')
            && ($payload['model'] ?? null) === 'claude-sonnet-5-5'
            && ($payload['max_tokens'] ?? null) === config('ai.providers.anthropic.max_tokens')
            && ($payload['system'] ?? null) === 'Ground every answer in project data.'
            && ! in_array('system', $roles, true)
            && $roles === ['user', 'assistant', 'user'];
    });
});

it('maps Anthropic HTTP status errors including 529 overloaded to S4 named exceptions without leaking the API key', function (
    int $status,
    array $body,
    string $expectedException,
    string $expectedMessage,
): void {
    $secretKey = 'sk-ant-leak-sentinel-6655-abc';

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            ...$body,
            'echoed_secret' => $secretKey,
        ], $status),
    ]);

    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::Anthropic,
        'model' => 'claude-sonnet-5-5',
        'api_key' => $secretKey,
    ]);

    try {
        app(AnthropicProvider::class)->chat($connection, [
            new AiChatMessage(AiMessageRole::User, 'Hi'),
        ]);
        $this->fail("Expected {$expectedException} to be thrown.");
    } catch (Throwable $exception) {
        expect($exception)->toBeInstanceOf($expectedException)
            ->and($exception->getMessage())->toBe($expectedMessage)
            ->and($exception->getMessage())->not->toContain($secretKey);
    }
})->with([
    '401 authentication_error' => [
        401,
        ['error' => ['type' => 'authentication_error', 'message' => 'invalid x-api-key']],
        AiKeyRejectedException::class,
        'Your Anthropic key was rejected. Update it in Settings → Integrations.',
    ],
    '403 permission_error' => [
        403,
        ['error' => ['type' => 'permission_error', 'message' => 'forbidden']],
        AiKeyRejectedException::class,
        'Your Anthropic key was rejected. Update it in Settings → Integrations.',
    ],
    '402 billing_error' => [
        402,
        ['error' => ['type' => 'billing_error', 'message' => 'Insufficient credits']],
        AiOutOfCreditException::class,
        'Your Anthropic account has no credit left.',
    ],
    '400 credit balance billing_error' => [
        400,
        ['error' => ['type' => 'billing_error', 'message' => 'Your credit balance is too low']],
        AiOutOfCreditException::class,
        'Your Anthropic account has no credit left.',
    ],
    '429 rate_limit_error' => [
        429,
        ['error' => ['type' => 'rate_limit_error', 'message' => 'Rate limited']],
        AiRateLimitedException::class,
        "You reached Anthropic's usage limit. Try again later.",
    ],
    '500 api_error' => [
        500,
        ['error' => ['type' => 'api_error', 'message' => 'Internal server error']],
        AiProviderUnavailableException::class,
        'Anthropic is not responding. Try again in a moment.',
    ],
    '529 overloaded_error' => [
        529,
        ['error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']],
        AiProviderUnavailableException::class,
        'Anthropic is not responding. Try again in a moment.',
    ],
]);

it('maps Anthropic connection timeouts to AiProviderUnavailableException without leaking the API key', function (): void {
    $secretKey = 'sk-ant-leak-sentinel-timeout-3322';

    Http::fake(function () use ($secretKey): never {
        throw new ConnectionException("Connection timed out for {$secretKey}");
    });

    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::Anthropic,
        'model' => 'claude-sonnet-5-5',
        'api_key' => $secretKey,
    ]);

    try {
        app(AnthropicProvider::class)->chat($connection, [
            new AiChatMessage(AiMessageRole::User, 'Hi'),
        ]);
        $this->fail('Expected AiProviderUnavailableException to be thrown.');
    } catch (AiProviderUnavailableException $exception) {
        expect($exception->getMessage())
            ->toBe('Anthropic is not responding. Try again in a moment.')
            ->not->toContain($secretKey);
    }
});

it('lets unexpected Anthropic HTTP errors fail without leaking the key or raw body', function (): void {
    $secretKey = 'sk-ant-leak-sentinel-400';
    $rawBodyMarker = 'ANTHROPIC_RAW_BODY_TOKEN';

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'error' => ['type' => 'invalid_request_error', 'message' => "{$rawBodyMarker} {$secretKey}"],
        ], 400),
    ]);

    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::Anthropic,
        'model' => 'claude-sonnet-5-5',
        'api_key' => $secretKey,
    ]);

    try {
        app(AnthropicProvider::class)->chat($connection, [
            new AiChatMessage(AiMessageRole::User, 'Hi'),
        ]);
        $this->fail('Expected RuntimeException to be thrown.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())
            ->not->toContain($secretKey)
            ->not->toContain($rawBodyMarker);
    }
});
