<?php

use App\Data\DashboardStatsData;
use App\Enums\TaskStatus;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('dashboard filters out earlier done tasks from today tasks and stats counters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $overdueDoneTask = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'due_date' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $overdueTodoTask = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => now()->subDay(),
    ]);

    $todayDoingTask = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Doing,
        'due_date' => now(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertViewHas('todayTasks', function (Collection $todayTasks) use ($overdueTodoTask, $overdueDoneTask): bool {
            return $todayTasks->contains($overdueTodoTask)
                && ! $todayTasks->contains($overdueDoneTask);
        })
        ->assertViewHas('stats', function (DashboardStatsData $stats): bool {
            return $stats->openTasksCount === 2
                && $stats->todayTasksCount === 1;
        });
});

test('America/New_York user sees task due on local day as Today, not Overdue, and counts in today_tasks_count', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create([
        'timezone' => 'America/New_York',
    ]);
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'NY Today Task',
        'due_date' => '2026-11-03 00:00:00',
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertViewHas('todayTasks', function (Collection $todayTasks) use ($task): bool {
            return $todayTasks->contains($task);
        })
        ->assertViewHas('stats', function (DashboardStatsData $stats): bool {
            return $stats->todayTasksCount === 1;
        })
        ->assertSee('Today')
        ->assertDontSee('Overdue');
});

test('America/New_York user sees task due on previous day as Overdue', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create([
        'timezone' => 'America/New_York',
    ]);
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'NY Overdue Task',
        'due_date' => '2026-11-02 00:00:00',
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertViewHas('todayTasks', function (Collection $todayTasks) use ($task): bool {
            return $todayTasks->contains($task);
        })
        ->assertSee('Overdue');
});

test('regression: UTC user sees task due on current day as Today, not Overdue', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create([
        'timezone' => 'UTC',
    ]);
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'UTC Today Task',
        'due_date' => '2026-11-04 00:00:00',
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertViewHas('todayTasks', function (Collection $todayTasks) use ($task): bool {
            return $todayTasks->contains($task);
        })
        ->assertViewHas('stats', function (DashboardStatsData $stats): bool {
            return $stats->todayTasksCount === 1;
        })
        ->assertSee('Today')
        ->assertDontSee('Overdue');
});

test('UTC user sees task due on previous day as Overdue', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create([
        'timezone' => 'UTC',
    ]);
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'UTC Overdue Task',
        'due_date' => '2026-11-03 00:00:00',
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertViewHas('todayTasks', function (Collection $todayTasks) use ($task): bool {
            return $todayTasks->contains($task);
        })
        ->assertSee('Overdue');
});

test('today widget displays the task title', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Visible Task Title In Today Widget',
        'due_date' => '2026-11-04 00:00:00',
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Visible Task Title In Today Widget');
});

test('dashboard displays project header progress and omits greeting and legacy today fallback', function () {
    $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));

    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'timezone' => 'UTC',
    ]);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-04 00:00:00',
    ]);
    Task::factory()->count(2)->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Todo,
        'due_date' => '2026-11-06 00:00:00',
    ]);
    InboxItem::factory()->count(4)->create([
        'project_id' => $project->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee($project->name)
        ->assertSee('0 of 3 tasks done')
        ->assertDontSee('¡Buenos días, '.$user->display_name.'!')
        ->assertViewHas('stats', fn (DashboardStatsData $stats): bool => $stats->todayTasksCount === 1 && $stats->inboxCount === 4);

    $project->tasks()->whereDate('due_date', '2026-11-04')->delete();

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Nothing due today')
        ->assertViewHas('stats', fn (DashboardStatsData $stats): bool => $stats->todayTasksCount === 0 && $stats->openTasksCount === 2);
});

test('today tasks excludes future and undated tasks and orders by due date ascending', function () {
    $this->travelTo(Carbon::parse('2026-11-04 12:00:00'));

    $user = User::factory()->create(['timezone' => 'UTC']);
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Future Task',
        'due_date' => '2026-11-05 00:00:00',
        'status' => TaskStatus::Todo,
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Undated Task',
        'due_date' => null,
        'status' => TaskStatus::Todo,
    ]);

    foreach (range(1, 6) as $dayOffset) {
        Task::factory()->create([
            'project_id' => $project->id,
            'title' => "Today Task {$dayOffset}",
            'due_date' => Carbon::parse('2026-10-28')->addDays($dayOffset),
            'status' => TaskStatus::Todo,
        ]);
    }

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSeeInOrder([
            'Today Task 1',
            'Today Task 2',
            'Today Task 3',
            'Today Task 4',
            'Today Task 5',
            'Today Task 6',
        ])
        ->assertDontSee('Future Task')
        ->assertDontSee('Undated Task')
        ->assertSeeHtml(route('project.tasks', $project));
});

test('dashboard shows empty states when there are no today tasks, areas, or recent activities', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Nothing due today')
        ->assertSee('No areas yet')
        ->assertSee('No recent activity')
        ->assertSee('Upload File')
        ->assertSeeHtml(route('project.tasks', $project));
});

test('dashboard displays up to five most recent activities from notebooks and files', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $oldestNotebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Oldest Notebook',
        'updated_at' => now()->subDays(6),
    ]);
    ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Activity 5 File',
        'updated_at' => now()->subDays(5),
    ]);
    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Activity 4 Notebook',
        'updated_at' => now()->subDays(4),
    ]);
    ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Activity 3 File',
        'updated_at' => now()->subDays(3),
    ]);
    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Activity 2 Notebook',
        'updated_at' => now()->subDays(2),
    ]);
    ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Activity 1 File',
        'updated_at' => now()->subDay(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSeeInOrder([
            'Activity 1 File',
            'Activity 2 Notebook',
            'Activity 3 File',
            'Activity 4 Notebook',
            'Activity 5 File',
        ])
        ->assertDontSee($oldestNotebook->name);
});

test('project header renders areas button without unused settings gear button', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Areas')
        ->assertDontSeeHtml('M7.84 1.804A1 1 0 0 1 8.82 1h2.36');
});
