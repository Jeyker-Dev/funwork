<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

function packFor(User $user, Project $project): string
{
    $response = test()->actingAs($user)->get(route('project.pack', $project));

    $response->assertOk();

    return $response->streamedContent();
}

test('the owner downloads the project pack as a markdown file', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create(['name' => 'Fun Work']);

    $response = $this->actingAs($owner)->get(route('project.pack', $project));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');
    $response->assertDownload('fun-work-project-pack.md');
    expect($response->streamedContent())->toStartWith('# Fun Work — Project pack');
});

test('the pack groups tasks by status with priority, due date and assignees', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $doing = Task::factory()->for($project)->create([
        'title' => 'Write the pack',
        'description' => 'Markdown export',
        'status' => TaskStatus::Doing,
        'priority' => TaskPriority::High,
        'due_date' => '2026-11-10',
    ]);
    $doing->users()->attach($owner);

    Task::factory()->for($project)->create([
        'title' => 'Ship it',
        'status' => TaskStatus::Done,
    ]);

    $pack = packFor($owner, $project);

    expect($pack)
        ->toContain('## Tareas')
        ->toContain('### Doing (1)')
        ->toContain("- **Write the pack** — prioridad: High · vence: 2026-11-10 · asignada a: {$owner->display_name}")
        ->toContain('  Markdown export')
        ->toContain('### Done (1)')
        ->toContain('### Todo (0)')
        ->and(strpos($pack, '### Todo'))->toBeLessThan(strpos($pack, '### Doing'))
        ->and(strpos($pack, '### Doing'))->toBeLessThan(strpos($pack, '### Done'));
});

test('the pack includes notebook pages in order and skips archived notebooks', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs',
        'created_by' => $owner->id,
        'is_archived' => false,
    ]);
    $notebook->pages()->create(['content' => ['title' => 'Second', 'body' => 'Body two'], 'order' => 2, 'created_by' => $owner->id]);
    $notebook->pages()->create(['content' => ['title' => 'First', 'body' => 'Body one'], 'order' => 1, 'created_by' => $owner->id]);
    $notebook->pages()->create(['content' => ['body' => 'No title'], 'order' => 3, 'created_by' => $owner->id]);

    $archived = Notebook::factory()->for($project)->create([
        'name' => 'Old ideas',
        'created_by' => $owner->id,
        'is_archived' => true,
    ]);
    $archived->pages()->create(['content' => ['title' => 'Hidden', 'body' => 'Hidden body'], 'order' => 1, 'created_by' => $owner->id]);

    $pack = packFor($owner, $project);

    expect($pack)
        ->toContain('## Notebooks')
        ->toContain('### Docs')
        ->toContain("#### First\n\nBody one")
        ->toContain("#### Página 3\n\nNo title")
        ->not->toContain('Old ideas')
        ->not->toContain('Hidden body')
        ->and(strpos($pack, '#### First'))->toBeLessThan(strpos($pack, '#### Second'));
});

test('an empty project still produces a pack', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    expect(packFor($owner, $project))
        ->toContain('_Sin tareas._')
        ->toContain('_Sin notebooks._');
});

test('the project page links to the pack download', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner, 'owner')->create();

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee(route('project.pack', $project))
        ->assertSee('Descargar Project pack');
});

test('another user cannot download the pack', function () {
    $project = Project::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('project.pack', $project))
        ->assertForbidden();
});

test('guests are redirected to the login page', function () {
    $project = Project::factory()->create();

    $this->get(route('project.pack', $project))->assertRedirect(route('login'));
});
