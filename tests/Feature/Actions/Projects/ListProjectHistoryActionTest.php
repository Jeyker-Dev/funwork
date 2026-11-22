<?php

use App\Actions\Projects\ListProjectHistoryAction;
use App\Enums\ActivityType;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Decision;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('collects only completed tasks, accepted decisions, created notebooks, and non-deleted files', function () {
    Carbon::setTestNow('2026-11-22 14:00:00');

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $backend = Area::factory()->create(['project_id' => $project->id, 'name' => 'Backend', 'color' => 'blue']);

    $doneTask = Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
        'title' => 'Connect the MCP inbox',
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-11-19 15:00:00'),
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Open Todo Task',
        'status' => TaskStatus::Todo,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Open Doing Task',
        'status' => TaskStatus::Doing,
    ]);

    $acceptedDecision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'number' => 12,
        'title' => 'Use Livewire single-file components',
        'decided_at' => '2026-11-18',
        'updated_at' => Carbon::parse('2026-11-18 12:00:00'),
    ]);

    Decision::factory()->proposed()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'number' => 13,
        'title' => 'Proposed Decision',
    ]);

    Decision::factory()->rejected()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'number' => 14,
        'title' => 'Rejected Decision',
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Architecture',
        'created_at' => Carbon::parse('2026-11-16 10:00:00'),
    ]);
    $firstPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'created_by' => $user->id,
        'order' => 1,
    ]);

    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'brand-guidelines.pdf',
        'created_at' => Carbon::parse('2026-11-13 11:00:00'),
    ]);

    $deletedFile = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'deleted-file.pdf',
        'created_at' => Carbon::parse('2026-11-20 11:00:00'),
    ]);
    $deletedFile->delete();

    $history = app(ListProjectHistoryAction::class)($project, null, 4, $user);

    expect($history->hasAnyEvents)->toBeTrue()
        ->and($history->hasMoreWeeks)->toBeFalse()
        ->and($history->weeks)->toHaveCount(2);

    $thisWeek = $history->weeks[0];
    expect($thisWeek->title)->toBe('This week')
        ->and($thisWeek->dateRange)->toBe('Nov 16–22')
        ->and($thisWeek->summary)->toBe('1 task done, 1 decision, 1 notebook')
        ->and($thisWeek->events)->toHaveCount(3);

    expect($thisWeek->events[0])
        ->type->toBe(ActivityType::Task)
        ->id->toBe($doneTask->id)
        ->linkText->toBe('Connect the MCP inbox')
        ->areaName->toBe('Backend')
        ->areaColor->toBe('blue')
        ->url->toBe(route('show.task', ['project' => $project, 'task' => $doneTask]));

    expect($thisWeek->events[1])
        ->type->toBe(ActivityType::Decision)
        ->id->toBe($acceptedDecision->id)
        ->linkText->toBe('#12 Use Livewire single-file components')
        ->url->toBe(route('project.decisions.show', ['project' => $project, 'decision' => $acceptedDecision]));

    expect($thisWeek->events[2])
        ->type->toBe(ActivityType::Notebook)
        ->id->toBe($notebook->id)
        ->linkText->toBe('Architecture')
        ->url->toBe(route('project.notebooks.pages.edit', ['project' => $project, 'notebook' => $notebook, 'page' => $firstPage]));

    $lastWeek = $history->weeks[1];
    expect($lastWeek->title)->toBe('Last week')
        ->and($lastWeek->dateRange)->toBe('Nov 9–15')
        ->and($lastWeek->summary)->toBe('1 file')
        ->and($lastWeek->events)->toHaveCount(1)
        ->and($lastWeek->events[0]->linkText)->toBe('brand-guidelines.pdf')
        ->and($lastWeek->events[0]->url)->toBe(route('project.files.preview', ['project' => $project, 'projectFile' => $file]));

    Carbon::setTestNow();
});

it('groups older weeks with date ranges, paginates by week limit, and omits zero counts', function () {
    Carbon::setTestNow('2026-11-22 14:00:00');

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    // Week 0 (This week: Nov 16-22)
    Task::factory()->count(2)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-11-20 10:00:00'),
    ]);

    // Week 1 (Last week: Nov 9-15)
    Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'decided_at' => '2026-11-11',
        'updated_at' => Carbon::parse('2026-11-11 10:00:00'),
    ]);

    // Week 2 (Nov 2-8)
    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'created_at' => Carbon::parse('2026-11-05 10:00:00'),
    ]);

    // Week 3 (Oct 26-Nov 1, cross-month)
    ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'created_at' => Carbon::parse('2026-10-30 10:00:00'),
    ]);

    // Week 4 (Oct 19-25)
    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-10-21 10:00:00'),
    ]);

    $firstPage = app(ListProjectHistoryAction::class)($project, null, 4, $user);

    expect($firstPage->weeks)->toHaveCount(4)
        ->and($firstPage->hasMoreWeeks)->toBeTrue()
        ->and($firstPage->weeks[0]->title)->toBe('This week')
        ->and($firstPage->weeks[0]->summary)->toBe('2 tasks done')
        ->and($firstPage->weeks[1]->title)->toBe('Last week')
        ->and($firstPage->weeks[2]->title)->toBe('Nov 2–8')
        ->and($firstPage->weeks[2]->dateRange)->toBeNull()
        ->and($firstPage->weeks[3]->title)->toBe('Oct 26–Nov 1');

    $allPages = app(ListProjectHistoryAction::class)($project, null, 8, $user);

    expect($allPages->weeks)->toHaveCount(5)
        ->and($allPages->hasMoreWeeks)->toBeFalse()
        ->and($allPages->weeks[4]->title)->toBe('Oct 19–25');

    Carbon::setTestNow();
});

it('respects user timezone for event weekdays and week boundaries', function () {
    Carbon::setTestNow('2026-11-23 02:00:00 UTC');

    $user = User::factory()->create(['timezone' => 'America/Caracas']); // UTC-4: 2026-11-22 22:00:00 (Sunday)
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Late night Sunday task',
        'status' => TaskStatus::Done,
        'completed_at' => Carbon::parse('2026-11-23 01:30:00', 'UTC'), // Sun 21:30 in Caracas
    ]);

    $decision = Decision::factory()->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'decided_at' => '2026-11-22', // Sunday calendar date must stay Sun in UTC-4
        'updated_at' => Carbon::parse('2026-11-22 14:00:00', 'UTC'),
    ]);

    $history = app(ListProjectHistoryAction::class)($project, null, 4, $user);

    expect($history->weeks)->toHaveCount(1)
        ->and($history->weeks[0]->title)->toBe('This week')
        ->and($history->weeks[0]->dateRange)->toBe('Nov 16–22')
        ->and($history->weeks[0]->events[0]->occurredAt->format('D'))->toBe('Sun')
        ->and($history->weeks[0]->events[1]->id)->toBe($decision->id)
        ->and($history->weeks[0]->events[1]->occurredAt->format('D'))->toBe('Sun');

    Carbon::setTestNow();
});

it('queries project history without N+1 queries', function () {
    Carbon::setTestNow('2026-11-22 14:00:00');

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $areas = Area::factory()->count(3)->create(['project_id' => $project->id]);

    foreach ($areas as $area) {
        Task::factory()->count(4)->create([
            'project_id' => $project->id,
            'area_id' => $area->id,
            'status' => TaskStatus::Done,
            'completed_at' => now()->subDay(),
        ]);
    }

    Decision::factory()->count(6)->accepted()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'decided_at' => '2026-11-20',
    ]);

    Notebook::factory()->count(6)->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ])->each(function (Notebook $notebook) use ($user): void {
        NotebookPage::factory()->create([
            'notebook_id' => $notebook->id,
            'created_by' => $user->id,
            'order' => 1,
        ]);
    });

    ProjectFile::factory()->count(6)->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $history = app(ListProjectHistoryAction::class)($project, null, 4, $user);

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($history->weeks->first()->events)->toHaveCount(30)
        ->and(count($queries))->toBeLessThanOrEqual(5);

    Carbon::setTestNow();
});
