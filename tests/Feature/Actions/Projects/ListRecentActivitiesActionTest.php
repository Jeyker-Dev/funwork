<?php

use App\Actions\Projects\ListRecentActivitiesAction;
use App\Enums\ActivityType;
use App\Enums\TaskStatus;
use App\Models\Decision;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it lists notebooks, files, decisions, and completed tasks newest first, tagged with their activity type', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $completedTask = Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Completed task',
        'status' => TaskStatus::Done,
        'updated_at' => now()->subDays(3),
    ]);

    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Open task ignored',
        'status' => TaskStatus::Todo,
        'updated_at' => now()->addMinute(),
    ]);

    $decision = Decision::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'title' => 'Adopt Luminous Editorial',
        'updated_at' => now()->subDays(2),
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'updated_at' => now()->subDay(),
    ]);

    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'updated_at' => now(),
    ]);

    $activities = (new ListRecentActivitiesAction)($project, 5);

    expect($activities)->toHaveCount(4)
        ->and($activities[0]->type)->toBe(ActivityType::File)
        ->and($activities[0]->id)->toBe($file->id)
        ->and($activities[1]->type)->toBe(ActivityType::Notebook)
        ->and($activities[1]->id)->toBe($notebook->id)
        ->and($activities[2]->type)->toBe(ActivityType::Decision)
        ->and($activities[2]->id)->toBe($decision->id)
        ->and($activities[2]->name)->toBe('Adopt Luminous Editorial')
        ->and($activities[3]->type)->toBe(ActivityType::Task)
        ->and($activities[3]->id)->toBe($completedTask->id)
        ->and($activities[3]->name)->toBe('Completed task');
});

test('it returns at most the requested number of activities', function () {
    $project = Project::factory()->create();
    Notebook::factory()->count(3)->create(['project_id' => $project->id]);

    expect((new ListRecentActivitiesAction)($project, 2))->toHaveCount(2);
});
