<?php

use App\Contracts\AiProvider as AiProviderContract;
use App\Enums\AiProvider;
use App\Models\AiConnection;
use App\Services\AiProviders\AiProviderResolver;
use App\Services\AiProviders\AnthropicProvider;
use App\Services\AiProviders\FakeAiProvider;
use App\Services\AiProviders\OpenAiCompatibleProvider;

test('resolver picks AnthropicProvider for Anthropic and OpenAiCompatibleProvider for all other providers', function (string $providerValue, string $expectedClass) {
    $connection = AiConnection::factory()->make([
        'provider' => AiProvider::from($providerValue),
        'base_url' => $providerValue === 'custom' ? 'https://llm.example.com/v1' : null,
    ]);

    $adapter = app(AiProviderResolver::class)->resolve($connection);

    expect($adapter)->toBeInstanceOf($expectedClass);
})->with([
    'openai' => ['openai', OpenAiCompatibleProvider::class],
    'anthropic' => ['anthropic', AnthropicProvider::class],
    'gemini' => ['gemini', OpenAiCompatibleProvider::class],
    'deepseek' => ['deepseek', OpenAiCompatibleProvider::class],
    'openrouter' => ['openrouter', OpenAiCompatibleProvider::class],
    'opencode' => ['opencode', OpenAiCompatibleProvider::class],
    'custom' => ['custom', OpenAiCompatibleProvider::class],
]);

test('container binding lets tests swap in FakeAiProvider through AiProviderResolver', function () {
    $fake = FakeAiProvider::fake();

    $openAiConnection = AiConnection::factory()->make(['provider' => AiProvider::OpenAi]);
    $anthropicConnection = AiConnection::factory()->make(['provider' => AiProvider::Anthropic]);

    $resolver = app(AiProviderResolver::class);

    expect($resolver->resolve($openAiConnection))->toBe($fake)
        ->and($resolver->resolve($anthropicConnection))->toBe($fake)
        ->and(app(AiProviderContract::class))->toBe($fake);
});
