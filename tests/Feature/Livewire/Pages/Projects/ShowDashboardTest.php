<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
