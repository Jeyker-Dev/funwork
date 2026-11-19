<?php

use App\Actions\Projects\ListRecentActivitiesAction;
use App\Enums\ActivityType;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it lists notebooks and files newest first, tagged with their activity type', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

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

    expect($activities)->toHaveCount(2)
        ->and($activities[0]->type)->toBe(ActivityType::File)
        ->and($activities[0]->id)->toBe($file->id)
        ->and($activities[1]->type)->toBe(ActivityType::Notebook)
        ->and($activities[1]->id)->toBe($notebook->id);
});

test('it returns at most the requested number of activities', function () {
    $project = Project::factory()->create();
    Notebook::factory()->count(3)->create(['project_id' => $project->id]);

    expect((new ListRecentActivitiesAction)($project, 2))->toHaveCount(2);
});
