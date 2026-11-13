<?php

use App\Enums\TaskStatus;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('dashboard filters out done tasks from urgent tasks and stats counters', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $overdueDoneTask = Task::factory()->create([
        'project_id' => $project->id,
        'status' => TaskStatus::Done,
        'due_date' => now()->subDay(),
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
        ->assertViewHas('urgentTasks', function ($urgentTasks) use ($overdueTodoTask, $overdueDoneTask) {
            return $urgentTasks->contains($overdueTodoTask)
                && ! $urgentTasks->contains($overdueDoneTask);
        })
        ->assertViewHas('stats', function ($stats) {
            return $stats['tasks_count'] === 2
                && $stats['today_tasks_count'] === 1;
        });
});

test('America/New_York user sees task due on local day as Due today, not Overdue, and counts in today_tasks_count', function () {
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
        ->assertViewHas('urgentTasks', function ($urgentTasks) use ($task) {
            return $urgentTasks->contains($task);
        })
        ->assertViewHas('stats', function ($stats) {
            return $stats['today_tasks_count'] === 1;
        })
        ->assertSee('Due today')
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
        ->assertViewHas('urgentTasks', function ($urgentTasks) use ($task) {
            return $urgentTasks->contains($task);
        })
        ->assertSee('Overdue')
        ->assertDontSee('Due today');
});

test('regression: UTC user sees task due on current day as Due today, not Overdue', function () {
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
        ->assertViewHas('urgentTasks', function ($urgentTasks) use ($task) {
            return $urgentTasks->contains($task);
        })
        ->assertViewHas('stats', function ($stats) {
            return $stats['today_tasks_count'] === 1;
        })
        ->assertSee('Due today')
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
        ->assertViewHas('urgentTasks', function ($urgentTasks) use ($task) {
            return $urgentTasks->contains($task);
        })
        ->assertSee('Overdue')
        ->assertDontSee('Due today');
});

test('urgent tasks widget displays the task title', function () {
    $this->travelTo(Carbon::parse('2026-11-04 02:00:00'));

    $user = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Visible Task Title In Urgent Widget',
        'due_date' => '2026-11-04 00:00:00',
        'status' => TaskStatus::Todo,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('Visible Task Title In Urgent Widget');
});

test('dashboard displays greeting, rendered stats, and falls back to total open tasks when today count is zero', function () {
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

    // When today_tasks_count > 0, displays today_tasks_count (1) and inbox_count (4)
    $html = Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('¡Buenos días, '.$user->display_name.'!')
        ->html();

    expect($html)->toContain('>1</span>')
        ->toContain('>4</span>');

    // When today_tasks_count === 0, falls back to total open tasks_count (2)
    $project->tasks()->whereDate('due_date', '2026-11-04')->delete();

    $fallbackHtml = Livewire::test('pages::projects.show', ['project' => $project])
        ->html();

    expect($fallbackHtml)->toContain('>2</span>');
});

test('urgent tasks excludes future and undated tasks, orders by due date ascending, and limits to five', function () {
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
            'title' => "Urgent Task {$dayOffset}",
            'due_date' => Carbon::parse('2026-10-28')->addDays($dayOffset),
            'status' => TaskStatus::Todo,
        ]);
    }

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSeeInOrder([
            'Urgent Task 1',
            'Urgent Task 2',
            'Urgent Task 3',
            'Urgent Task 4',
            'Urgent Task 5',
        ])
        ->assertDontSee('Urgent Task 6')
        ->assertDontSee('Future Task')
        ->assertDontSee('Undated Task')
        ->assertSeeHtml(route('project.tasks', $project));
});

test('dashboard shows empty states when there are no urgent tasks or recent activities', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.show', ['project' => $project])
        ->assertSee('No urgent tasks!')
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
