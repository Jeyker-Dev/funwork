<?php

use App\Enums\AiProvider;
use App\Models\AiConnection;
use App\Models\User;
use Livewire\Livewire;

test('integrations page renders the AI connections card at the top with callouts and disabled companion row', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)
        ->get(route('integrations.edit'))
        ->assertSuccessful()
        ->assertSeeLivewire('settings.ai-connections')
        ->assertSee('AI connections')
        ->assertSee('What gets sent')
        ->assertSee('A ChatGPT Plus or Claude Pro subscription does not include API access. Create an API key in the provider\'s developer console.')
        ->assertSee('Your computer (FunWork Companion)')
        ->assertSee('Coming soon')
        ->getContent();

    $aiConnectionsPos = strpos($html, 'id="ai-connections"');
    $connectAgentPos = strpos($html, 'Connect your AI agent');

    expect($aiConnectionsPos)->not->toBeFalse()
        ->and($connectAgentPos)->not->toBeFalse()
        ->and($aiConnectionsPos)->toBeLessThan($connectAgentPos);
});

test('user can create, edit, make default, and remove AI connections without ever leaking the api_key', function () {
    $user = User::factory()->create();
    $firstSecret = 'sk-or-v1-super-secret-first-7f3a';
    $secondSecret = 'sk-proj-super-secret-second-9b2c';

    $component = Livewire::actingAs($user)
        ->test('settings.ai-connections')
        ->set('provider', AiProvider::OpenRouter->value)
        ->assertSet('allowPaidModels', false)
        ->assertSee('Allow paid models when free models fail')
        ->assertDontSee('Base URL')
        ->set('model', 'meta-llama/llama-3.3-70b-instruct:free')
        ->set('apiKey', $firstSecret)
        ->call('save')
        ->assertHasNoErrors();

    $first = AiConnection::query()->where('user_id', $user->id)->firstOrFail();

    expect($first->provider)->toBe(AiProvider::OpenRouter)
        ->and($first->allow_paid_models)->toBeFalse()
        ->and($first->is_default)->toBeTrue()
        ->and($first->api_key)->toBe($firstSecret);

    // Key is cleared from public state and never appears in rendered HTML or Livewire snapshot.
    $component->assertSet('apiKey', '')
        ->assertSee($first->maskedApiKey())
        ->assertSee('Default')
        ->assertDontSeeHtml($firstSecret);

    expect(json_encode($component->snapshot))->not->toContain($firstSecret);

    // Create a second connection (non-OpenRouter: OpenRouter switch disappears; Custom shows Base URL).
    $component->set('provider', AiProvider::OpenAi->value)
        ->assertDontSee('Allow paid models when free models fail')
        ->set('model', 'gpt-5')
        ->set('apiKey', $secondSecret)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('apiKey', '')
        ->assertDontSeeHtml($secondSecret);

    expect(json_encode($component->snapshot))->not->toContain($secondSecret);

    $second = AiConnection::query()
        ->where('user_id', $user->id)
        ->where('id', '!=', $first->id)
        ->firstOrFail();

    expect($second->is_default)->toBeFalse()
        ->and($second->allow_paid_models)->toBeFalse();

    // Make default switches Default pill.
    $component->call('makeDefault', $second->id)
        ->assertHasNoErrors();

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();

    // Edit with empty key preserves existing key; editing model works with free-text fallback too.
    $component->call('edit', $first->id)
        ->assertSet('apiKey', '')
        ->assertDontSeeHtml($firstSecret)
        ->set('model', 'custom/my-free-model:free')
        ->set('allowPaidModels', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('apiKey', '')
        ->assertDontSeeHtml($firstSecret);

    expect($first->fresh()->api_key)->toBe($firstSecret)
        ->and($first->fresh()->model)->toBe('custom/my-free-model:free')
        ->and($first->fresh()->allow_paid_models)->toBeTrue()
        ->and(json_encode($component->snapshot))->not->toContain($firstSecret);

    // Remove with confirmation promotes the remaining connection if default was deleted.
    $component->call('confirmRemove', $second->id)
        ->call('removeConnection')
        ->assertHasNoErrors();

    expect(AiConnection::find($second->id))->toBeNull()
        ->and($first->fresh()->is_default)->toBeTrue();
});

test('Custom provider shows Base URL input, validates https, and clears apiKey even on failure', function () {
    $user = User::factory()->create();
    $secret = 'sk-custom-never-leak-5555';

    $component = Livewire::actingAs($user)
        ->test('settings.ai-connections')
        ->set('provider', AiProvider::Custom->value)
        ->assertSee('Base URL')
        ->assertDontSee('Allow paid models when free models fail')
        ->set('baseUrl', 'http://not-https.example.com/v1')
        ->set('model', 'llama-3.3')
        ->set('apiKey', $secret)
        ->call('save')
        ->assertHasErrors(['baseUrl'])
        ->assertSet('apiKey', '')
        ->assertDontSeeHtml($secret);

    expect(json_encode($component->snapshot))->not->toContain($secret);

    $component->set('baseUrl', 'https://llm.example.com/v1')
        ->set('apiKey', $secret)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('apiKey', '')
        ->assertDontSeeHtml($secret);

    expect(json_encode($component->snapshot))->not->toContain($secret)
        ->and(AiConnection::query()->where('user_id', $user->id)->firstOrFail()->base_url)->toBe('https://llm.example.com/v1');
});

test('user cannot see another users connections and receives 403 on every action', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $ownerConnection = AiConnection::factory()->create([
        'user_id' => $owner->id,
        'label' => 'Owners Private Model',
        'api_key' => 'sk-owner-secret-9999',
    ]);

    Livewire::actingAs($intruder)
        ->test('settings.ai-connections')
        ->assertDontSee('Owners Private Model')
        ->assertDontSee($ownerConnection->maskedApiKey())
        ->call('edit', $ownerConnection->id)
        ->assertForbidden();

    Livewire::actingAs($intruder)
        ->test('settings.ai-connections')
        ->call('makeDefault', $ownerConnection->id)
        ->assertForbidden();

    Livewire::actingAs($intruder)
        ->test('settings.ai-connections')
        ->call('confirmRemove', $ownerConnection->id)
        ->assertForbidden();
});
