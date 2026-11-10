<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('an authenticated user can create an API token and sees the plaintext token once', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::settings.integrations')
        ->set('tokenName', 'Antigravity Laptop')
        ->call('createToken')
        ->assertHasNoErrors();

    $plainTextToken = $component->get('plainTextToken');

    expect($plainTextToken)
        ->toBeString()
        ->toStartWith(ApiToken::TOKEN_PREFIX);

    $storedToken = ApiToken::where('user_id', $user->id)->first();

    expect($storedToken)
        ->not->toBeNull()
        ->name->toBe('Antigravity Laptop')
        ->token->toBe(ApiToken::hashToken($plainTextToken))
        ->token->not->toBe($plainTextToken);

    $component->call('closeCreatedTokenModal')
        ->assertSet('plainTextToken', null);
});

test('creating a token requires a valid name', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.integrations')
        ->set('tokenName', '')
        ->call('createToken')
        ->assertHasErrors(['tokenName' => 'required']);

    expect(ApiToken::where('user_id', $user->id)->count())->toBe(0);
});

test('the integrations page lists only the current user tokens and revokes them', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $tokenA = ApiToken::factory()->for($userA)->create(['name' => 'Token User A']);
    $tokenB = ApiToken::factory()->for($userB)->create(['name' => 'Token User B']);

    Livewire::actingAs($userA)
        ->test('pages::settings.integrations')
        ->assertSee('Token User A')
        ->assertDontSee('Token User B')
        ->call('confirmRevokeToken', $tokenA->id)
        ->call('revokeToken')
        ->assertDontSee('Token User A');

    expect(ApiToken::find($tokenA->id))->toBeNull()
        ->and(ApiToken::find($tokenB->id))->not->toBeNull();
});

test('a user cannot revoke another user API token', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $tokenA = ApiToken::factory()->for($userA)->create();

    $this->expectException(ModelNotFoundException::class);

    Livewire::actingAs($userB)
        ->test('pages::settings.integrations')
        ->call('confirmRevokeToken', $tokenA->id);
});

test('the MCP HTTP endpoint rejects requests without a Bearer token', function () {
    $this->postJson(route('mcp.funwork'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])->assertUnauthorized();
});

test('the MCP HTTP endpoint rejects requests with an invalid Bearer token', function () {
    $this->withHeader('Authorization', 'Bearer invalid-token')
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertUnauthorized();
});

test('the MCP HTTP endpoint authenticates a valid Bearer token and records last used timestamp', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)->execute($user, 'Antigravity CLI');

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'list_projects'])
        ->assertJsonFragment(['name' => 'list_tasks'])
        ->assertJsonFragment(['name' => 'get_task'])
        ->assertJsonFragment(['name' => 'update_task_status'])
        ->assertJsonFragment(['name' => 'add_task'])
        ->assertJsonFragment(['name' => 'add_inbox_item'])
        ->assertJsonFragment(['name' => 'read_notebook']);

    expect($created->apiToken->fresh()->last_used_at)->not->toBeNull();
});

test('the MCP HTTP endpoint rejects an expired Bearer token', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)->execute($user, 'Antigravity CLI');

    $created->apiToken->update(['expires_at' => now()->subMinute()]);

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertUnauthorized();
});

test('the MCP HTTP endpoint rejects a revoked Bearer token', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)->execute($user, 'Antigravity CLI');

    $created->apiToken->delete();

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertUnauthorized();
});
