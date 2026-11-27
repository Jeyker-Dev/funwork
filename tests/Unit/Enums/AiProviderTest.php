<?php

use App\Enums\AiProvider;
use Tests\TestCase;

uses(TestCase::class);

test('AiProvider defines the expected cases and labels', function (string $value, string $expectedLabel) {
    expect(AiProvider::from($value)->label())->toBe($expectedLabel);
})->with([
    'openai' => ['openai', 'OpenAI'],
    'anthropic' => ['anthropic', 'Anthropic'],
    'gemini' => ['gemini', 'Google Gemini'],
    'deepseek' => ['deepseek', 'DeepSeek'],
    'openrouter' => ['openrouter', 'OpenRouter'],
    'opencode' => ['opencode', 'OpenCode'],
    'custom' => ['custom', 'Custom (OpenAI-compatible)'],
]);

test('preset providers resolve their default base URL from config/ai.php and Custom returns null', function () {
    foreach (AiProvider::cases() as $provider) {
        if ($provider === AiProvider::Custom) {
            expect($provider->defaultBaseUrl())->toBeNull()
                ->and($provider->isCustom())->toBeTrue();
        } else {
            expect($provider->defaultBaseUrl())
                ->toBe(config("ai.providers.{$provider->value}.base_url"))
                ->toStartWith('https://')
                ->and($provider->isCustom())->toBeFalse();
        }
    }
});

test('only OpenRouter reports isOpenRouter as true', function () {
    foreach (AiProvider::cases() as $provider) {
        expect($provider->isOpenRouter())->toBe($provider === AiProvider::OpenRouter);
    }
});

test('OpenRouter separates :free models and paid fallback models in config/ai.php', function () {
    $freeModels = AiProvider::OpenRouter->suggestedModels();
    $paidModels = AiProvider::OpenRouter->paidFallbackModels();

    expect($freeModels)->not->toBeEmpty()
        ->and($paidModels)->not->toBeEmpty();

    foreach ($freeModels as $model) {
        expect($model)->toEndWith(':free');
    }

    foreach ($paidModels as $model) {
        expect($model)->not->toEndWith(':free');
    }
});

test('other preset providers return non-empty suggested models from config and Custom returns empty', function () {
    foreach (AiProvider::cases() as $provider) {
        if ($provider === AiProvider::Custom) {
            expect($provider->suggestedModels())->toBe([]);
        } else {
            expect($provider->suggestedModels())->not->toBeEmpty();
        }
    }
});
