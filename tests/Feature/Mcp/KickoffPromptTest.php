<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Mcp\Prompts\KickoffPrompt;
use App\Mcp\Servers\FunWorkServer;
use App\Models\User;

test('FunWorkServer lists kickoff prompt with an optional idea argument', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)($user, 'Prompt Client');

    $response = $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/list',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'kickoff']);

    $kickoff = collect($response->json('result.prompts'))
        ->firstWhere('name', 'kickoff');

    expect($kickoff)->not->toBeNull()
        ->and($kickoff['arguments'])->toHaveCount(1)
        ->and($kickoff['arguments'][0]['name'])->toBe('idea')
        ->and($kickoff['arguments'][0]['required'])->toBeFalse();
});

test('kickoff prompt guides discovery, planning, confirmation before writing, and final brief summary', function () {
    $user = User::factory()->create();

    FunWorkServer::actingAs($user)
        ->prompt(KickoffPrompt::class, [
            'idea' => 'A personal habit tracker with weekly reviews.',
        ])
        ->assertOk()
        ->assertName('kickoff')
        ->assertSee([
            'A personal habit tracker with weekly reviews.',
            'Problem',
            'Goal',
            'Users',
            'Scope',
            'Stack',
            'Do not call any write tools yet',
            'Vision',
            'log_decision',
            'ask the user to confirm',
            'create_project',
            'create_notebook',
            'add_notebook_page',
            'add_tasks',
            'get_project_brief',
        ]);
});

test('kickoff prompt works without the optional idea argument', function () {
    $user = User::factory()->create();

    FunWorkServer::actingAs($user)
        ->prompt(KickoffPrompt::class)
        ->assertOk()
        ->assertName('kickoff')
        ->assertSee([
            'asking what they want to build',
            'ask the user to confirm',
        ]);
});
