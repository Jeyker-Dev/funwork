<?php

use App\Actions\Auth\CreateApiTokenAction;
use App\Actions\Projects\CreateDefaultAreasAction;
use App\Data\Mcp\AddNotebookPageData;
use App\Mcp\Servers\FunWorkServer;
use App\Mcp\Tools\AddNotebookPageTool;
use App\Mcp\Tools\CreateNotebookTool;
use App\Mcp\Tools\ReadNotebookTool;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;

test('FunWorkServer registers create_notebook and add_notebook_page', function () {
    $user = User::factory()->create();
    $created = app(CreateApiTokenAction::class)($user, 'Notebook Client');

    $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'create_notebook'])
        ->assertJsonFragment(['name' => 'add_notebook_page']);
});

test('create_notebook creates a notebook for the project owner with optional description and area', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);
    $backendArea = $project->areaByKey('backend');

    $created = app(CreateApiTokenAction::class)($user, 'Notebook Creator');

    $response = $this->withHeader('Authorization', 'Bearer '.$created->plainTextToken)
        ->postJson(route('mcp.funwork'), [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'create_notebook',
                'arguments' => [
                    'project_id' => $project->id,
                    'name' => 'Architecture Notes',
                    'description' => 'Core design and vision',
                    'area' => 'BACKEND',
                ],
            ],
        ])
        ->assertSuccessful();

    $payload = json_decode((string) $response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR);
    $notebook = Notebook::find($payload['id']);

    expect($notebook)->not->toBeNull()
        ->and($notebook->project_id)->toBe($project->id)
        ->and($notebook->created_by)->toBe($user->id)
        ->and($notebook->name)->toBe('Architecture Notes')
        ->and($notebook->description)->toBe('Core design and vision')
        ->and($notebook->area_id)->toBe($backendArea->id)
        ->and($payload['area']['slug'])->toBe('backend');
});

test('create_notebook allows omitting description and area', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::actingAs($user)
        ->tool(CreateNotebookTool::class, [
            'project_id' => $project->id,
            'name' => 'Docs',
        ])
        ->assertOk()
        ->assertName('create_notebook')
        ->assertSee('Docs');

    $notebook = Notebook::where('project_id', $project->id)->first();

    expect($notebook)->not->toBeNull()
        ->and($notebook->description)->toBeNull()
        ->and($notebook->area_id)->toBeNull();
});

test('create_notebook rejects creating a notebook in another user project', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    FunWorkServer::actingAs($intruder)
        ->tool(CreateNotebookTool::class, [
            'project_id' => $project->id,
            'name' => 'Unauthorized Notebook',
            'area' => 'nonexistent',
        ])
        ->assertHasErrors(['You are not authorized to create notebooks in this project.']);

    expect(Notebook::where('project_id', $project->id)->exists())->toBeFalse();
});

test('create_notebook rejects an unknown area slug', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    FunWorkServer::actingAs($user)
        ->tool(CreateNotebookTool::class, [
            'project_id' => $project->id,
            'name' => 'Unknown Area Notebook',
            'area' => 'nonexistent',
        ])
        ->assertHasErrors(["Area [nonexistent] not found in project [{$project->id}]."]);

    expect(Notebook::where('project_id', $project->id)->exists())->toBeFalse();
});

test('create_notebook rejects unauthenticated requests', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    FunWorkServer::tool(CreateNotebookTool::class, [
        'project_id' => $project->id,
        'name' => 'Unauthenticated Notebook',
    ])
        ->assertHasErrors(['Unauthenticated.']);

    expect(Notebook::count())->toBe(0);
});

test('create_notebook returns validation errors for missing or invalid fields', function (array $arguments) {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    if (array_key_exists('project_id', $arguments) && $arguments['project_id'] === 'VALID_PROJECT') {
        $arguments['project_id'] = $project->id;
    }

    FunWorkServer::actingAs($user)
        ->tool(CreateNotebookTool::class, $arguments)
        ->assertHasErrors();

    expect(Notebook::count())->toBe(0);
})->with([
    'missing project_id' => [['name' => 'Valid']],
    'missing name' => [['project_id' => 'VALID_PROJECT']],
    'empty name' => [['project_id' => 'VALID_PROJECT', 'name' => '']],
    'name too long' => [['project_id' => 'VALID_PROJECT', 'name' => str_repeat('a', 256)]],
]);

test('add_notebook_page appends Markdown pages with sequential order for the owner', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Docs',
    ]);

    FunWorkServer::actingAs($user)
        ->tool(AddNotebookPageTool::class, [
            'notebook_id' => $notebook->id,
            'title' => 'Vision',
            'body' => "# Product Vision\n\nBuild a clean workspace.",
        ])
        ->assertOk()
        ->assertName('add_notebook_page')
        ->assertSee(['Vision', '# Product Vision', 'Build a clean workspace.']);

    FunWorkServer::actingAs($user)
        ->tool(AddNotebookPageTool::class, [
            'notebook_id' => $notebook->id,
            'title' => 'Scope',
            'body' => '- Item 1',
        ])
        ->assertOk();

    $pages = NotebookPage::where('notebook_id', $notebook->id)->orderBy('order')->get();

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->order)->toBe(1)
        ->and($pages[0]->created_by)->toBe($user->id)
        ->and($pages[0]->content)->toBe([
            'title' => 'Vision',
            'body' => "# Product Vision\n\nBuild a clean workspace.",
        ])
        ->and($pages[1]->order)->toBe(2)
        ->and($pages[1]->content)->toBe([
            'title' => 'Scope',
            'body' => '- Item 1',
        ]);

    FunWorkServer::actingAs($user)
        ->tool(ReadNotebookTool::class, [
            'notebook_id' => $notebook->id,
        ])
        ->assertOk()
        ->assertSee(['## Vision', 'Build a clean workspace.', '## Scope', '- Item 1']);
});

test('add_notebook_page rejects adding a page to another user notebook', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    FunWorkServer::actingAs($intruder)
        ->tool(AddNotebookPageTool::class, [
            'notebook_id' => $notebook->id,
            'title' => 'Intruder Page',
            'body' => 'Should not persist.',
        ])
        ->assertHasErrors(['You are not authorized to add pages to this notebook.']);

    expect(NotebookPage::where('notebook_id', $notebook->id)->exists())->toBeFalse();
});

test('add_notebook_page rejects unauthenticated requests', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    FunWorkServer::tool(AddNotebookPageTool::class, [
        'notebook_id' => $notebook->id,
        'title' => 'Unauthenticated Page',
        'body' => 'Body',
    ])
        ->assertHasErrors(['Unauthenticated.']);

    expect(NotebookPage::count())->toBe(0);
});

test('add_notebook_page returns validation errors for missing or invalid fields', function (array $arguments) {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    if (array_key_exists('notebook_id', $arguments) && $arguments['notebook_id'] === 'VALID_NOTEBOOK') {
        $arguments['notebook_id'] = $notebook->id;
    }

    FunWorkServer::actingAs($user)
        ->tool(AddNotebookPageTool::class, $arguments)
        ->assertHasErrors();

    expect(NotebookPage::count())->toBe(0);
})->with([
    'missing notebook_id' => [['title' => 'Vision', 'body' => 'Content']],
    'missing title' => [['notebook_id' => 'VALID_NOTEBOOK', 'body' => 'Content']],
    'empty title' => [['notebook_id' => 'VALID_NOTEBOOK', 'title' => '', 'body' => 'Content']],
    'title too long' => [['notebook_id' => 'VALID_NOTEBOOK', 'title' => str_repeat('a', 256), 'body' => 'Content']],
    'body too long' => [['notebook_id' => 'VALID_NOTEBOOK', 'title' => 'Vision', 'body' => str_repeat('a', AddNotebookPageData::MAX_BODY_LENGTH + 1)]],
    'missing body' => [['notebook_id' => 'VALID_NOTEBOOK', 'title' => 'Vision']],
    'empty body' => [['notebook_id' => 'VALID_NOTEBOOK', 'title' => 'Vision', 'body' => '']],
]);
