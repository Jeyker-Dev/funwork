<?php

use App\Actions\AiConnections\CreateAiConnectionAction;
use App\Actions\AiConnections\DeleteAiConnectionAction;
use App\Actions\AiConnections\MakeDefaultAiConnectionAction;
use App\Actions\AiConnections\UpdateAiConnectionAction;
use App\Data\AiConnections\AiConnectionData;
use App\Enums\AiProvider;
use App\Exceptions\AiConnections\EmptyAiApiKeyException;
use App\Exceptions\AiConnections\EmptyAiModelException;
use App\Exceptions\AiConnections\InvalidAiBaseUrlException;
use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

it('makes the first connection default and keeps subsequent ones non-default', function () {
    $user = User::factory()->create();
    $create = app(CreateAiConnectionAction::class);

    $first = $create($user, new AiConnectionData(
        provider: AiProvider::OpenAi,
        model: 'gpt-5',
        apiKey: 'sk-first-key-1111',
    ));

    $second = $create($user, new AiConnectionData(
        provider: AiProvider::Anthropic,
        model: 'claude-sonnet-5-5',
        apiKey: 'sk-ant-second-2222',
    ));

    expect($first->is_default)->toBeTrue()
        ->and($second->is_default)->toBeFalse()
        ->and($user->aiConnections()->where('is_default', true)->count())->toBe(1);
});

it('switches the default connection exclusively via MakeDefaultAiConnectionAction', function () {
    $user = User::factory()->create();
    $create = app(CreateAiConnectionAction::class);
    $makeDefault = app(MakeDefaultAiConnectionAction::class);

    $first = $create($user, new AiConnectionData(
        provider: AiProvider::OpenAi,
        model: 'gpt-5',
        apiKey: 'sk-first-1111',
    ));

    $second = $create($user, new AiConnectionData(
        provider: AiProvider::DeepSeek,
        model: 'deepseek-chat',
        apiKey: 'sk-ds-2222',
    ));

    $makeDefault($second, $user);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and($user->aiConnections()->where('is_default', true)->count())->toBe(1);
});

it('promotes the most recent remaining connection when deleting the default', function () {
    $user = User::factory()->create();
    $create = app(CreateAiConnectionAction::class);
    $delete = app(DeleteAiConnectionAction::class);

    $first = $create($user, new AiConnectionData(
        provider: AiProvider::OpenAi,
        model: 'gpt-5',
        apiKey: 'sk-first-1111',
    ));

    $second = $create($user, new AiConnectionData(
        provider: AiProvider::Gemini,
        model: 'gemini-3.6-flash',
        apiKey: 'AIza-second-2222',
    ));

    $third = $create($user, new AiConnectionData(
        provider: AiProvider::OpenRouter,
        model: 'meta-llama/llama-3.3-70b-instruct:free',
        apiKey: 'sk-or-third-3333',
    ));

    $delete($first, $user);

    expect(AiConnection::find($first->id))->toBeNull()
        ->and($third->fresh()->is_default)->toBeTrue()
        ->and($second->fresh()->is_default)->toBeFalse()
        ->and($user->aiConnections()->where('is_default', true)->count())->toBe(1);
});

it('requires an https base_url for Custom and ignores base_url for preset providers', function () {
    $user = User::factory()->create();
    $create = app(CreateAiConnectionAction::class);

    $preset = $create($user, new AiConnectionData(
        provider: AiProvider::OpenAi,
        baseUrl: 'https://evil.example.com/v1',
        model: 'gpt-5',
        apiKey: 'sk-openai-1234',
    ));

    expect($preset->base_url)->toBeNull()
        ->and($preset->effectiveBaseUrl())->toBe(config('ai.providers.openai.base_url'));

    expect(fn () => $create($user, new AiConnectionData(
        provider: AiProvider::Custom,
        baseUrl: 'http://insecure.example.com/v1',
        model: 'llama-3',
        apiKey: 'sk-custom-1234',
    )))->toThrow(InvalidAiBaseUrlException::class);

    expect(fn () => $create($user, new AiConnectionData(
        provider: AiProvider::Custom,
        baseUrl: null,
        model: 'llama-3',
        apiKey: 'sk-custom-1234',
    )))->toThrow(InvalidAiBaseUrlException::class);

    $custom = $create($user, new AiConnectionData(
        provider: AiProvider::Custom,
        baseUrl: 'https://llm.internal.example/v1/',
        model: 'llama-3',
        apiKey: 'sk-custom-1234',
    ));

    expect($custom->base_url)->toBe('https://llm.internal.example/v1')
        ->and($custom->effectiveBaseUrl())->toBe('https://llm.internal.example/v1');
});

it('forces allow_paid_models to false for non-OpenRouter providers and defaults false for OpenRouter', function () {
    $user = User::factory()->create();
    $create = app(CreateAiConnectionAction::class);
    $update = app(UpdateAiConnectionAction::class);

    $openAi = $create($user, new AiConnectionData(
        provider: AiProvider::OpenAi,
        model: 'gpt-5',
        apiKey: 'sk-openai-1234',
        allowPaidModels: true,
    ));

    $openRouterDefault = $create($user, new AiConnectionData(
        provider: AiProvider::OpenRouter,
        model: 'meta-llama/llama-3.3-70b-instruct:free',
        apiKey: 'sk-or-1234',
    ));

    $openRouterPaid = $update($openRouterDefault, $user, new AiConnectionData(
        provider: AiProvider::OpenRouter,
        model: 'meta-llama/llama-3.3-70b-instruct:free',
        allowPaidModels: true,
    ));

    expect($openAi->allow_paid_models)->toBeFalse()
        ->and($openRouterPaid->allow_paid_models)->toBeTrue();

    // Switching back from OpenRouter to another provider forces allow_paid_models to false.
    $switched = $update($openRouterPaid, $user, new AiConnectionData(
        provider: AiProvider::Anthropic,
        model: 'claude-sonnet-5-5',
        allowPaidModels: true,
    ));

    expect($switched->allow_paid_models)->toBeFalse();
});

it('keeps the previous api_key when updating with an empty key and replaces it when non-empty', function () {
    $user = User::factory()->create();
    $create = app(CreateAiConnectionAction::class);
    $update = app(UpdateAiConnectionAction::class);

    $connection = $create($user, new AiConnectionData(
        provider: AiProvider::OpenRouter,
        label: 'Primary',
        model: 'meta-llama/llama-3.3-70b-instruct:free',
        apiKey: 'sk-or-original-9999',
    ));

    $updatedWithoutKey = $update($connection, $user, new AiConnectionData(
        provider: AiProvider::OpenRouter,
        label: 'Renamed',
        model: 'deepseek/deepseek-r1:free',
        apiKey: '   ',
        allowPaidModels: true,
    ));

    expect($updatedWithoutKey->fresh()->api_key)->toBe('sk-or-original-9999')
        ->and($updatedWithoutKey->label)->toBe('Renamed')
        ->and($updatedWithoutKey->model)->toBe('deepseek/deepseek-r1:free')
        ->and($updatedWithoutKey->allow_paid_models)->toBeTrue();

    $updatedWithNewKey = $update($connection, $user, new AiConnectionData(
        provider: AiProvider::OpenRouter,
        label: 'Renamed',
        model: 'deepseek/deepseek-r1:free',
        apiKey: 'sk-or-rotated-8888',
        allowPaidModels: false,
    ));

    expect($updatedWithNewKey->fresh()->api_key)->toBe('sk-or-rotated-8888')
        ->and($updatedWithNewKey->allow_paid_models)->toBeFalse();
});

it('throws named exceptions when api_key is missing on create or model is empty', function () {
    $user = User::factory()->create();
    $create = app(CreateAiConnectionAction::class);

    expect(fn () => $create($user, new AiConnectionData(
        provider: AiProvider::OpenAi,
        model: 'gpt-5',
        apiKey: '   ',
    )))->toThrow(EmptyAiApiKeyException::class);

    expect(fn () => $create($user, new AiConnectionData(
        provider: AiProvider::OpenAi,
        model: '   ',
        apiKey: 'sk-openai-1234',
    )))->toThrow(EmptyAiModelException::class);
});

it('forbids another user from updating, deleting, or making default another users connection', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $connection = AiConnection::factory()->create(['user_id' => $owner->id]);

    expect(fn () => app(UpdateAiConnectionAction::class)($connection, $intruder, new AiConnectionData(
        provider: AiProvider::OpenAi,
        model: 'gpt-5',
        apiKey: 'sk-hack-1234',
    )))->toThrow(AuthorizationException::class);

    expect(fn () => app(DeleteAiConnectionAction::class)($connection, $intruder))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(MakeDefaultAiConnectionAction::class)($connection, $intruder))
        ->toThrow(AuthorizationException::class);
});
