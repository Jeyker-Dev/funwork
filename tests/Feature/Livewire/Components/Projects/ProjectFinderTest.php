<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders project finder dropdown for current project', function () {
    $user = User::factory()->create();
    $currentProject = Project::factory()->create(['name' => 'Current Project', 'owner_id' => $user->id]);
    $otherProject = Project::factory()->create(['name' => 'Other Project', 'owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.project-finder', ['project' => $currentProject])
        ->assertSee('Current Project')
        ->assertSee('Other Project');
});

test('excludes current project from search results', function () {
    $user = User::factory()->create();
    $currentProject = Project::factory()->create(['name' => 'Current Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Alpha Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Beta Project', 'owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.project-finder', ['project' => $currentProject])
        ->set('search', 'Alpha')
        ->assertSee('Alpha Project')
        ->assertDontSee('Beta Project');
});

test('searching for percent wildcard returns only project with literal percent and not every project', function () {
    $user = User::factory()->create();
    $currentProject = Project::factory()->create(['name' => 'Current Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Project 100%', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Other Project', 'owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.project-finder', ['project' => $currentProject])
        ->set('search', '%')
        ->assertSee('Project 100%')
        ->assertDontSee('Other Project');
});

test('searching for underscore wildcard matches only a project whose name literally contains underscore', function () {
    $user = User::factory()->create();
    $currentProject = Project::factory()->create(['name' => 'Current Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'project_alpha', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'projectalpha', 'owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.project-finder', ['project' => $currentProject])
        ->set('search', '_')
        ->assertSee('project_alpha')
        ->assertDontSee('projectalpha');
});

test('regression: searching for normal text continues to return matching projects', function () {
    $user = User::factory()->create();
    $currentProject = Project::factory()->create(['name' => 'Current Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Alpha Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Beta Project', 'owner_id' => $user->id]);

    Livewire::actingAs($user)
        ->test('projects.project-finder', ['project' => $currentProject])
        ->set('search', 'Alpha')
        ->assertSee('Alpha Project')
        ->assertDontSee('Beta Project');
});
