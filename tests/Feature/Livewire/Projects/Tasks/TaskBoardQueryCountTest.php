<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

test('task board query count does not grow with the number of tasks or assignees', function () {
    $user = User::factory()->create();
    $assignees = User::factory()->count(2)->create();

    // Case 1: 4 tasks per column (slider flyout is mounted)
    $projectA = Project::factory()->create(['owner_id' => $user->id]);

    foreach (TaskStatus::cases() as $status) {
        for ($i = 1; $i <= 4; $i++) {
            $task = Task::factory()->create([
                'project_id' => $projectA->id,
                'status' => $status->value,
                'title' => "BoardA-{$status->value}-Task-{$i}",
                'position' => $i,
            ]);
            $task->users()->attach($assignees);
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $componentA = Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $projectA]);

    $queriesCountA = count(DB::getQueryLog());
    DB::disableQueryLog();

    $componentA->assertOk();

    // Case 2: 20 tasks per column, each with 2 assigned users
    $projectB = Project::factory()->create(['owner_id' => $user->id]);
    $visibleTitlesB = [];

    foreach (TaskStatus::cases() as $status) {
        for ($i = 1; $i <= 20; $i++) {
            $title = "BoardB-{$status->value}-Task-{$i}";
            $task = Task::factory()->create([
                'project_id' => $projectB->id,
                'status' => $status->value,
                'title' => $title,
                'position' => $i,
            ]);
            $task->users()->attach($assignees);

            if ($i <= 3) {
                $visibleTitlesB[] = $title;
            }
        }
    }

    DB::flushQueryLog();
    DB::enableQueryLog();

    $componentB = Livewire::actingAs($user)
        ->test('projects.tasks.⚡task-board', ['project' => $projectB]);

    $queriesCountB = count(DB::getQueryLog());
    DB::disableQueryLog();

    $componentB->assertOk();

    // Query count must remain identical regardless of task or assignee volume
    $this->assertSame($queriesCountA, $queriesCountB);

    // Verify the board still displays the 3 visible tasks for each column
    foreach ($visibleTitlesB as $title) {
        $componentB->assertSee($title);
    }
});
