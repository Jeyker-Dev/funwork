<?php

use App\Actions\Tasks\ParseTaskImportAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

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
    $this->action = new ParseTaskImportAction;
});

test('it parses valid line with prefix, area, criterion and dependency', function () {
    $text = '- [ ] [BACKEND] Setup database schema — migration runs without errors — depende de: Docker';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeTrue()
        ->and($item->status)->toBe(TaskStatus::Todo)
        ->and($item->priority)->toBe(TaskPriority::Low)
        ->and($item->areaTag)->toBe('BACKEND')
        ->and($item->areaId)->toBe($this->backendArea->id)
        ->and($item->areaName)->toBe('Backend')
        ->and($item->title)->toBe('Setup database schema')
        ->and($item->criterion)->toBe('migration runs without errors')
        ->and($item->dependency)->toBe('Docker')
        ->and($item->description())->toBe("migration runs without errors\n\nDepende de: Docker")
        ->and($item->errors)->toBeEmpty()
        ->and($item->warnings)->toBeEmpty();
});

test('it parses valid line without prefix', function () {
    $text = '[BACKEND] Build API endpoint — returns 200 with valid JSON';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeTrue()
        ->and($item->status)->toBe(TaskStatus::Todo)
        ->and($item->title)->toBe('Build API endpoint')
        ->and($item->criterion)->toBe('returns 200 with valid JSON')
        ->and($item->dependency)->toBeNull()
        ->and($item->description())->toBe('returns 200 with valid JSON');
});

test('it parses line with - [x] prefix as Done status', function () {
    $text = '- [x] [BACKEND] Completed task — all tests green';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeTrue()
        ->and($item->status)->toBe(TaskStatus::Done)
        ->and($item->title)->toBe('Completed task');
});

test('it supports em dash, en dash and double dash separators', function (string $separator) {
    $text = "- [ ] [BACKEND] Task title {$separator} Acceptance criterion {$separator} depende de: Core";

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeTrue()
        ->and($item->title)->toBe('Task title')
        ->and($item->criterion)->toBe('Acceptance criterion')
        ->and($item->dependency)->toBe('Core');
})->with([
    'em dash' => '—',
    'en dash' => '–',
    'double hyphen' => '--',
]);

test('it handles missing area without errors or warnings', function () {
    $text = '- [ ] Task without area — Acceptance criterion';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeTrue()
        ->and($item->areaTag)->toBeNull()
        ->and($item->areaId)->toBeNull()
        ->and($item->title)->toBe('Task without area')
        ->and($item->criterion)->toBe('Acceptance criterion')
        ->and($item->warnings)->toBeEmpty();
});

test('it produces a warning when area is not found but still imports without area', function () {
    $text = '- [ ] [QA] Write E2E tests — Cypress suite passes';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeTrue()
        ->and($item->areaTag)->toBe('QA')
        ->and($item->areaId)->toBeNull()
        ->and($item->warnings)->toContain("Area 'QA' not found — will be imported without area");
});

test('it produces an error when acceptance criterion is missing', function () {
    $text = '- [ ] [BACKEND] Task without criterion';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeFalse()
        ->and($item->errors)->toContain('Missing acceptance criterion');
});

test('it produces an error when title is longer than 255 characters', function () {
    $longTitle = str_repeat('a', 256);
    $text = "- [ ] [BACKEND] {$longTitle} — Acceptance criterion";

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeFalse()
        ->and($item->errors)->toContain('Title cannot be longer than 255 characters');
});

test('it silently skips blank lines', function () {
    $text = '

    - [ ] [BACKEND] Task 1 — Criterion 1

       
    - [ ] [BACKEND] Task 2 — Criterion 2

    ';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(2)
        ->and($results[0]->title)->toBe('Task 1')
        ->and($results[1]->title)->toBe('Task 2');
});

test('it treats depende de: - as no dependency', function () {
    $text = '- [ ] [BACKEND] Independent task — Acceptance criterion — depende de: -';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeTrue()
        ->and($item->dependency)->toBeNull()
        ->and($item->description())->toBe('Acceptance criterion');
});

test('it warns when title already exists in project case-insensitively', function () {
    Task::factory()->create([
        'project_id' => $this->project->id,
        'title' => 'Existing Feature Task',
    ]);

    $text = '- [ ] [BACKEND] existing feature task — New criterion';

    $results = ($this->action)($this->project, $text);

    expect($results)->toHaveCount(1);
    $item = $results[0];

    expect($item->isValid())->toBeTrue()
        ->and($item->warnings)->toContain('Title already exists in the project (possible duplicate)');
});
