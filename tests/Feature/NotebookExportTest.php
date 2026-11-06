<?php

use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;

function exportFor(User $user, Project $project, Notebook $notebook): string
{
    $response = test()->actingAs($user)->get(route('project.notebooks.export', [$project, $notebook]));

    $response->assertOk();

    return $response->streamedContent();
}

test('the owner downloads the notebook as a markdown file', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create(['name' => 'Fun Work']);
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Architecture Notes',
        'created_by' => $owner->id,
    ]);

    $response = $this->actingAs($owner)->get(route('project.notebooks.export', [$project, $notebook]));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
    $response->assertDownload('architecture-notes.md');
    expect($response->streamedContent())->toStartWith('# Architecture Notes');
});

test('the export includes notebook pages in order and supports pages without titles', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs',
        'description' => 'Project documentation',
        'created_by' => $owner->id,
    ]);

    $notebook->pages()->create([
        'content' => ['title' => 'Second page', 'body' => 'Body of second'],
        'order' => 2,
        'created_by' => $owner->id,
    ]);
    $notebook->pages()->create([
        'content' => ['title' => 'First page', 'body' => 'Body of first'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);
    $notebook->pages()->create([
        'content' => ['body' => 'Page without title'],
        'order' => 3,
        'created_by' => $owner->id,
    ]);

    $content = exportFor($owner, $project, $notebook);

    expect($content)
        ->toStartWith("# Docs\n\nGenerado por FunWork el ")
        ->toContain('Project documentation')
        ->toContain("## First page\n\nBody of first")
        ->toContain("## Second page\n\nBody of second")
        ->toContain("## Página 3\n\nPage without title")
        ->and(strpos($content, '## First page'))->toBeLessThan(strpos($content, '## Second page'))
        ->and(strpos($content, '## Second page'))->toBeLessThan(strpos($content, '## Página 3'));
});

test('an empty notebook produces Sin paginas indicator', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Empty Notebook',
        'created_by' => $owner->id,
    ]);

    expect(exportFor($owner, $project, $notebook))
        ->toContain('_Sin páginas._');
});

test('another user cannot download the notebook export', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();
    $notebook = Notebook::factory()->for($project)->create([
        'created_by' => $owner->id,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('project.notebooks.export', [$project, $notebook]))
        ->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();
    $notebook = Notebook::factory()->for($project)->create();

    $this->get(route('project.notebooks.export', [$project, $notebook]))
        ->assertRedirect(route('login'));
});

test('the project dashboard no longer shows Project pack', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertDontSee('Project pack')
        ->assertDontSee('Descargar Project pack');
});
