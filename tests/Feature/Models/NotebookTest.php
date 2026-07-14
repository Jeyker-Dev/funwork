<?php

use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;

test('a notebook belongs to a project', function () {
    $project = Project::factory()->create();
    $notebook = Notebook::factory()->create(['project_id' => $project->id]);

    expect($notebook->project)->toBeInstanceOf(Project::class)
        ->and($notebook->project->id)->toBe($project->id);
});

test('a notebook belongs to a creator', function () {
    $user = User::factory()->create();
    $notebook = Notebook::factory()->create(['created_by' => $user->id]);

    expect($notebook->creator)->toBeInstanceOf(User::class)
        ->and($notebook->creator->id)->toBe($user->id);
});

test('it allows mass assignment for fillable attributes', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create();

    $notebook = Notebook::create([
        'project_id' => $project->id,
        'name' => 'My Notebook',
        'description' => 'Notebook description',
        'created_by' => $user->id,
        'is_archived' => true,
        'is_shared' => true,
    ]);

    expect($notebook->name)->toBe('My Notebook')
        ->and($notebook->description)->toBe('Notebook description')
        ->and($notebook->project_id)->toBe($project->id)
        ->and($notebook->created_by)->toBe($user->id)
        ->and($notebook->is_archived)->toBeTrue()
        ->and($notebook->is_shared)->toBeTrue();
});
