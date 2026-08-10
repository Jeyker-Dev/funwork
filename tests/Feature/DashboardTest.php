<?php

use App\Models\Notebook;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('projects'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('projects'));
    $response->assertOk();
});

test('project returns recent activities combined from notebooks and files', function () {
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

    $activities = $project->getRecentActivities();

    expect($activities)->toHaveCount(2);
    // The most recently updated should be first (the file)
    expect($activities[0]->type)->toBe('file');
    expect($activities[0]->id)->toBe($file->id);

    expect($activities[1]->type)->toBe('notebook');
    expect($activities[1]->id)->toBe($notebook->id);
});
