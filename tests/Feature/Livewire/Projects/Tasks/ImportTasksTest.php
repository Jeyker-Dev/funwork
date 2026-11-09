<?php

use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create(['owner_id' => $this->user->id]);
    $this->backendArea = Area::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'Backend',
        'slug' => 'backend',
        'color' => 'green',
        'position' => 1,
    ]);
});

test('it renders successfully for the project owner', function () {
    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->assertStatus(200)
        ->assertSee('Import');
});

test('an unauthorized user receives 403 on mount', function () {
    $otherUser = User::factory()->create();

    Livewire::actingAs($otherUser)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->assertForbidden();
});

test('an unauthorized user receives 403 on import action', function () {
    $otherUser = User::factory()->create();

    $component = Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', "- [ ] [BACKEND] Valid task — Valid criterion\n")
        ->call('preview');

    Livewire::actingAs($otherUser);
    $component->call('import')
        ->assertForbidden();

    expect(Task::where('title', 'Valid task')->exists())->toBeFalse();
});

test('it rejects more than 50 non-blank lines with validation error on text', function () {
    $text = collect(range(1, 51))
        ->map(fn (int $index): string => "- [ ] Task {$index} — Criterion {$index}")
        ->implode("\n");

    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', $text)
        ->call('preview')
        ->assertHasErrors(['text'])
        ->assertSet('step', 1);
});

test('preview displays errors and warnings for each line', function () {
    Task::factory()->create([
        'project_id' => $this->project->id,
        'title' => 'Existing Duplicate Title',
    ]);

    $text = <<<'TEXT'
    - [ ] [BACKEND] Task 1 — Criterion 1
    - [ ] [QA] Task with unknown area — Criterion 2
    - [ ] Task with invalid criterion
    - [ ] [BACKEND] Existing Duplicate Title — Another criterion
    TEXT;

    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', $text)
        ->call('preview')
        ->assertSet('step', 2)
        ->assertSee('Task 1')
        ->assertSee('Task with unknown area')
        ->assertSee("Area 'QA' not found — will be imported without area")
        ->assertSee('Missing acceptance criterion')
        ->assertSee('Title already exists in the project (possible duplicate)');
});

test('importing creates valid tasks and skips invalid lines', function () {
    $text = <<<'TEXT'
    - [ ] [BACKEND] Valid task — Valid criterion
    - [ ] Invalid task without criterion
    TEXT;

    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', $text)
        ->call('preview')
        ->call('import');

    expect(Task::where('title', 'Valid task')->exists())->toBeTrue()
        ->and(Task::where('title', 'Invalid task without criterion')->exists())->toBeFalse();
});

test('importing appends after max position per status', function () {
    Task::factory()->create([
        'project_id' => $this->project->id,
        'status' => TaskStatus::Todo,
        'position' => 5,
    ]);

    $text = <<<'TEXT'
    - [ ] [BACKEND] First Todo — Criterion 1
    - [x] First Done — Criterion 2
    - [ ] [BACKEND] Second Todo — Criterion 3
    TEXT;

    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', $text)
        ->call('preview')
        ->call('import');

    $todo1 = Task::where('title', 'First Todo')->first();
    $todo2 = Task::where('title', 'Second Todo')->first();
    $done1 = Task::where('title', 'First Done')->first();

    expect($todo1->position)->toBe(6)
        ->and($todo2->position)->toBe(7)
        ->and($done1->position)->toBe(1);
});

test('importing assigns current user to created tasks', function () {
    $text = '- [ ] [BACKEND] Assigned task — Criterion';

    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', $text)
        ->call('preview')
        ->call('import');

    $task = Task::where('title', 'Assigned task')->first();
    expect($task->users)->toHaveCount(1)
        ->and($task->users->first()->id)->toBe($this->user->id);
});

test('importing stores dependency in description when present', function () {
    $text = '- [ ] [BACKEND] Dependent task — Main criterion — depende de: Migration';

    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', $text)
        ->call('preview')
        ->call('import');

    $task = Task::where('title', 'Dependent task')->first();
    expect($task->description)->toBe("Main criterion\n\nDepende de: Migration");
});

test('importing dispatches task-created event and resets modal state', function () {
    $text = '- [ ] Task to test dispatch — Criterion';

    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', $text)
        ->call('preview')
        ->call('import')
        ->assertDispatched('task-created')
        ->assertSet('step', 1)
        ->assertSet('text', '')
        ->assertSet('parsedLines', []);
});

test('user can navigate back from preview to edit text', function () {
    Livewire::actingAs($this->user)
        ->test('projects.tasks.⚡import-tasks', ['project' => $this->project])
        ->set('text', "- [ ] Task 1 — Criterion 1\n")
        ->call('preview')
        ->assertSet('step', 2)
        ->call('back')
        ->assertSet('step', 1)
        ->assertSet('text', "- [ ] Task 1 — Criterion 1\n");
});
