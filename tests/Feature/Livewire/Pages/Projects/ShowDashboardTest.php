<?php

use App\Enums\TaskStatus;
use App\Models\Project;
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
