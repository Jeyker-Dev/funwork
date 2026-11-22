<?php

use App\Data\DashboardStatsData;
use App\Enums\DecisionStatus;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Decision;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('header displays project details, single progress bar, and task status breakdown without greeting or template chrome', function () {
    $this->travelTo(Carbon::parse('2026-11-22 12:00:00'));

    $user = User::factory()->create(['name' => 'Alex Rivera']);
    $project = Project::factory()->create([
        'owner_id' => $user->id,
        'name' => 'Website Relaunch',
        'icon' => 'wrench',
        'type' => 'Design',
        'due_at' => '2026-12-10 00:00:00',
    ]);

    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => TaskStatus::Todo]);
    Task::factory()->count(1)->create(['project_id' => $project->id, 'status' => TaskStatus::Doing]);
    Task::factory()->count(3)->create(['project_id' => $project->id, 'status' => TaskStatus::Done]);

    $this->actingAs($user);

    $html = Livewire::test('pages::projects.show', ['project' => $project])
        ->assertOk()
        ->assertSee('Website Relaunch')
        ->assertSee('Design')
        ->assertSee('Due Dec 10, 2026')
        ->assertSee('3 of 6 tasks done')
        ->assertSee('Todo 2')
        ->assertSee('Doing 1')
        ->assertSee('Done 3')
        ->assertDontSee('Buenos días')
        ->assertDontSee('Tareas para hoy')
        ->assertDontSee('Ideas en Inbox')
        ->assertDontSee('active sprint')
        ->assertDontSee('form-line')
        ->assertDontSee('line-card')
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $progressBars = $xpath->query('//*[@role="progressbar"]');
    $typeTags = $xpath->query('//*[@data-project-type-tag]');
    $ringCircles = $xpath->query('//*[@aria-label="Project overview"]//svg//circle');

    expect($progressBars->length)->toBe(1)
        ->and($progressBars->item(0)->getAttribute('aria-valuenow'))->toBe('50')
        ->and($typeTags->length)->toBe(1)
        ->and($typeTags->item(0)->getAttribute('class'))->not->toContain('bg-brand')
        ->and($ringCircles->length)->toBe(0);
});

test('today card lists overdue and due-today open tasks plus tasks completed today, excluding future, undated, and older done tasks', function () {
    $this->travelTo(Carbon::parse('2026-11-22 14:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $backend = Area::factory()->create([
        'project_id' => $project->id,
        'name' => 'Backend',
        'slug' => 'backend',
        'color' => 'blue',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
        'title' => 'Overdue API task',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-21 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Due today UI task',
        'status' => TaskStatus::Doing,
        'due_date' => '2026-11-22 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Completed today task',
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-22 00:00:00',
        'updated_at' => '2026-11-22 10:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Completed yesterday task',
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-21 00:00:00',
        'updated_at' => '2026-11-21 16:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Future task',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-25 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Undated task',
        'status' => TaskStatus::Todo,
        'due_date' => null,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Overdue API task')
        ->assertSee('Backend')
        ->assertSee('Overdue')
        ->assertSee('Due today UI task')
        ->assertSee('Today')
        ->assertSee('Completed today task')
        ->assertSee('Upcoming')
        ->assertSee('Future task')
        ->assertSee('Undated task')
        ->assertSeeHtml(route('project.tasks', $project))
        ->assertSee('View all tasks')
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $todaySectionText = $xpath->query('//*[@aria-labelledby="today-heading"]')->item(0)?->textContent ?? '';

    expect($todaySectionText)->toContain('Overdue API task')
        ->and($todaySectionText)->toContain('Due today UI task')
        ->and($todaySectionText)->toContain('Completed today task')
        ->and($todaySectionText)->toContain('Upcoming')
        ->and($todaySectionText)->toContain('Future task')
        ->and($todaySectionText)->toContain('Undated task')
        ->and($todaySectionText)->not->toContain('Completed yesterday task')
        ->and($html)->toContain('line-through')
        ->and($html)->toContain('text-danger');
});

test('checking a task in today marks it done and keeps it visible as completed', function () {
    $this->travelTo(Carbon::parse('2026-11-22 14:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Ship today card',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-22 00:00:00',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('0 of 1 tasks done')
        ->assertSee('Todo 1')
        ->assertSee('Done 0')
        ->call('toggleTaskCompletion', $task->id)
        ->assertHasNoErrors()
        ->assertSee('Ship today card')
        ->assertSee('1 of 1 tasks done')
        ->assertSee('Todo 0')
        ->assertSee('Done 1');

    expect($task->fresh()->status)->toBe(TaskStatus::Done);
});

test('unauthorized user cannot mark a task as done', function () {
    $this->travelTo(Carbon::parse('2026-11-22 14:00:00'));

    $owner = User::factory()->create(['timezone' => 'UTC']);
    $intruder = User::factory()->create(['timezone' => 'UTC']);

    $ownerProject = Project::factory()->create(['owner_id' => $owner->id]);
    $intruderProject = Project::factory()->create(['owner_id' => $intruder->id]);

    $protectedTask = Task::factory()->create([
        'project_id' => $ownerProject->id,
        'title' => 'Protected task',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-22 00:00:00',
    ]);

    Livewire::actingAs($intruder)
        ->test('pages::projects.show', ['project' => $ownerProject])
        ->assertForbidden();

    $component = Livewire::actingAs($owner)
        ->test('pages::projects.show', ['project' => $ownerProject]);

    $ownerProject->update(['owner_id' => $intruder->id]);

    $component->call('toggleTaskCompletion', $protectedTask->id)
        ->assertForbidden();

    $ownerProject->update(['owner_id' => $owner->id]);

    expect(fn () => Livewire::actingAs($intruder)
        ->test('pages::projects.show', ['project' => $intruderProject])
        ->call('toggleTaskCompletion', $protectedTask->id))
        ->toThrow(ModelNotFoundException::class);

    expect($protectedTask->fresh()->status)->toBe(TaskStatus::Todo);
});

test('empty today card says nothing is due today and shows up next for the earliest open task', function () {
    $this->travelTo(Carbon::parse('2026-11-22 12:00:00')); // Sunday

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Already finished early',
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-23 00:00:00',
        'updated_at' => '2026-11-20 12:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Later task',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-27 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Write onboarding notes',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-25 00:00:00', // Wednesday
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Nothing due today')
        ->assertSee('Upcoming')
        ->assertSeeInOrder([
            'Write onboarding notes',
            'Due Wednesday',
            'Later task',
            'Due Friday',
        ])
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $todaySectionText = $xpath->query('//*[@aria-labelledby="today-heading"]')->item(0)?->textContent ?? '';

    expect($todaySectionText)->not->toContain('Already finished early');
});

test('areas card displays each area with done and total task counts and renders an empty state when project has no areas', function () {
    $user = User::factory()->create();
    $projectWithAreas = Project::factory()->create(['owner_id' => $user->id]);

    $backend = Area::factory()->create([
        'project_id' => $projectWithAreas->id,
        'name' => 'Backend',
        'slug' => 'backend',
        'color' => 'blue',
        'position' => 1,
    ]);
    $design = Area::factory()->create([
        'project_id' => $projectWithAreas->id,
        'name' => 'Design',
        'slug' => 'design',
        'color' => 'green',
        'position' => 2,
    ]);

    Task::factory()->create(['project_id' => $projectWithAreas->id, 'area_id' => $backend->id, 'status' => TaskStatus::Done]);
    Task::factory()->count(2)->create(['project_id' => $projectWithAreas->id, 'area_id' => $backend->id, 'status' => TaskStatus::Todo]);
    Task::factory()->count(2)->create(['project_id' => $projectWithAreas->id, 'area_id' => $design->id, 'status' => TaskStatus::Done]);

    $emptyProject = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $projectWithAreas])
        ->assertSee('Backend')
        ->assertSee('1 of 3 tasks done')
        ->assertSee('Design')
        ->assertSee('2 of 2 tasks done');

    Livewire::test('pages::projects.show', ['project' => $emptyProject])
        ->assertSee('No areas yet');
});

test('recent activity card displays mixed feed of notebooks, decisions, files, and completed tasks without view all activity link', function () {
    $this->travelTo(Carbon::parse('2026-11-22 12:00:00'));

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'API notes',
        'updated_at' => now()->subMinutes(10),
    ]);
    Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'title' => 'Use Livewire SFC',
        'status' => DecisionStatus::Accepted,
        'updated_at' => now()->subHour(),
    ]);
    ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'brand-guide.pdf',
        'updated_at' => now()->subHours(2),
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Ship design tokens',
        'status' => TaskStatus::Done,
        'updated_at' => now()->subHours(3),
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Open task excluded from activity',
        'status' => TaskStatus::Todo,
        'due_date' => null,
        'updated_at' => now()->subMinute(),
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSeeInOrder([
            'Edited notebook',
            'API notes',
            'Logged decision',
            'Use Livewire SFC',
            'Uploaded file',
            'brand-guide.pdf',
            'Completed task',
            'Ship design tokens',
        ])
        ->assertDontSee('View all activity')
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $activityText = $xpath->query('//*[@aria-labelledby="recent-activity-heading"]')->item(0)?->textContent ?? '';

    expect($activityText)->not->toContain('Open task excluded from activity');
});

test('legacy highlightedTasksCount fallback is removed and never shown when today has no tasks', function () {
    $this->travelTo(Carbon::parse('2026-11-22 12:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->count(3)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-26 00:00:00',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Nothing due today')
        ->assertDontSee('Tareas para hoy')
        ->assertDontSee('for today');

    expect(method_exists(DashboardStatsData::class, 'highlightedTasksCount'))->toBeFalse();
});

test('project home loads in constant queries without n+1 regardless of areas and tasks', function () {
    $this->travelTo(Carbon::parse('2026-11-22 12:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $areas = Area::factory()->count(8)->create(['project_id' => $project->id]);
    foreach ($areas as $area) {
        Task::factory()->count(3)->create([
            'project_id' => $project->id,
            'area_id' => $area->id,
            'status' => TaskStatus::Todo,
            'due_date' => '2026-11-22 00:00:00',
        ]);
    }

    $this->actingAs($user);

    DB::enableQueryLog();
    DB::flushQueryLog();

    Livewire::test('pages::projects.show', ['project' => $project])->assertOk();

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(12);
});

test('areas without tasks show No tasks yet without an empty progress bar', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $backend = Area::factory()->create([
        'project_id' => $project->id,
        'name' => 'Backend',
        'slug' => 'backend',
        'color' => 'blue',
        'position' => 1,
    ]);
    $docs = Area::factory()->create([
        'project_id' => $project->id,
        'name' => 'Docs',
        'slug' => 'docs',
        'color' => 'green',
        'position' => 2,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
        'status' => TaskStatus::Done,
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Backend')
        ->assertSee('1 of 1 tasks done')
        ->assertSee('Docs')
        ->assertSee('No tasks yet')
        ->assertDontSee('0 of 0 tasks done')
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $areaProgressBars = $xpath->query('//*[@aria-labelledby="areas-heading"]//*[@data-area-progress]');
    expect($areaProgressBars->length)->toBe(1);
});

test('today card shows upcoming open tasks under an Upcoming label when Today has spare room', function () {
    $this->travelTo(Carbon::parse('2026-11-22 12:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Due today task',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-22 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Upcoming dated task',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-25 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Upcoming undated task',
        'status' => TaskStatus::Doing,
        'due_date' => null,
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Completed earlier task',
        'status' => TaskStatus::Done,
        'due_date' => '2026-11-21 00:00:00',
        'updated_at' => '2026-11-21 12:00:00',
    ]);

    $this->actingAs($user);

    $html = Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Today')
        ->assertSee('Due today task')
        ->assertSee('Upcoming')
        ->assertSeeInOrder(['Due today task', 'Upcoming', 'Upcoming dated task', 'Upcoming undated task'])
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $todaySectionText = $xpath->query('//*[@aria-labelledby="today-heading"]')->item(0)?->textContent ?? '';

    expect($todaySectionText)->toContain('Upcoming')
        ->and($todaySectionText)->toContain('Upcoming dated task')
        ->and($todaySectionText)->toContain('Upcoming undated task')
        ->and($todaySectionText)->not->toContain('Completed earlier task');
});

test('today card omits Upcoming when Today already has enough tasks', function () {
    $this->travelTo(Carbon::parse('2026-11-22 12:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->count(5)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-22 00:00:00',
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Future task hidden when full',
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-25 00:00:00',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertDontSee('Upcoming')
        ->assertDontSee('Future task hidden when full');
});
