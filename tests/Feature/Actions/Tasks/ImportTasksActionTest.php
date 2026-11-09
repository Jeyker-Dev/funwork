<?php

use App\Actions\Tasks\ImportTasksAction;
use App\Actions\Tasks\ParseTaskImportAction;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

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
    $this->parser = new ParseTaskImportAction;
    $this->action = new ImportTasksAction;
});

test('it creates valid tasks and skips invalid lines', function () {
    $text = <<<'TEXT'
    - [ ] [BACKEND] Valid task — Valid criterion
    - [ ] Invalid task without criterion
    TEXT;

    $parsedLines = $this->parser->execute($this->project, $text);
    $this->action->execute($this->project, $parsedLines, $this->user);

    expect(Task::where('title', 'Valid task')->exists())->toBeTrue()
        ->and(Task::where('title', 'Invalid task without criterion')->exists())->toBeFalse();
});

test('it appends position after max position per status', function () {
    Task::factory()->create([
        'project_id' => $this->project->id,
        'status' => TaskStatus::Todo,
        'position' => 3,
    ]);

    $text = <<<'TEXT'
    - [ ] [BACKEND] First Todo — Criterion 1
    - [x] First Done — Criterion 2
    - [ ] [BACKEND] Second Todo — Criterion 3
    TEXT;

    $parsedLines = $this->parser->execute($this->project, $text);
    $this->action->execute($this->project, $parsedLines, $this->user);

    $todo1 = Task::where('title', 'First Todo')->first();
    $todo2 = Task::where('title', 'Second Todo')->first();
    $done1 = Task::where('title', 'First Done')->first();

    expect($todo1->position)->toBe(4)
        ->and($todo2->position)->toBe(5)
        ->and($done1->position)->toBe(1);
});

test('it assigns importing user to tasks', function () {
    $text = '- [ ] [BACKEND] Task — Criterion';

    $parsedLines = $this->parser->execute($this->project, $text);
    $this->action->execute($this->project, $parsedLines, $this->user);

    $task = Task::where('title', 'Task')->first();
    expect($task->users)->toHaveCount(1)
        ->and($task->users->first()->id)->toBe($this->user->id);
});

test('it stores dependency in description when present', function () {
    $text = '- [ ] [BACKEND] Task — Criterion — depende de: Auth';

    $parsedLines = $this->parser->execute($this->project, $text);
    $this->action->execute($this->project, $parsedLines, $this->user);

    $task = Task::where('title', 'Task')->first();
    expect($task->description)->toBe("Criterion\n\nDepende de: Auth");
});

test('it associates resolved area with task', function () {
    $text = '- [ ] [BACKEND] Task with area — Criterion';

    $parsedLines = $this->parser->execute($this->project, $text);
    $this->action->execute($this->project, $parsedLines, $this->user);

    $task = Task::where('title', 'Task with area')->first();
    expect($task->area_id)->toBe($this->backendArea->id);
});

test('it throws authorization exception and does not create tasks when user is unauthorized', function () {
    $otherUser = User::factory()->create();

    $text = '- [ ] Task 1 — Criterion 1';
    $parsedLines = $this->parser->execute($this->project, $text);

    expect(fn () => $this->action->execute($this->project, $parsedLines, $otherUser))
        ->toThrow(AuthorizationException::class);

    expect(Task::count())->toBe(0);
});
