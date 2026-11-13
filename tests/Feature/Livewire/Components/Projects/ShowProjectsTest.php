<?php

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('renders a list of projects with correct information', function () {
    $user = User::factory()->create(['name' => 'John Doe']);
    $project = Project::factory()->create([
        'name' => 'Awesome Project',
        'description' => 'A very awesome project.',
        'status' => 'active',
        'due_at' => Carbon::now()->addDays(5)->format('Y-m-d H:i:s'),
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->assertSee('Awesome Project')
        ->assertSee('A very awesome project.')
        ->assertSee($user->display_name)
        ->assertSee('active')
        ->assertSee(Carbon::parse($project->due_at)->format('Y-m-d'));
});

test('filters projects by search property', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'Alpha Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Beta Project', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->set('search', 'Alpha')
        ->assertSee('Alpha Project')
        ->assertDontSee('Beta Project');
});

test('reacts to search-updated event', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'Alpha Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Beta Project', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->dispatch('search-updated', search: 'Beta')
        ->assertSet('search', 'Beta')
        ->assertSee('Beta Project')
        ->assertDontSee('Alpha Project');
});

test('reacts to project-created event by refreshing data', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('projects.show-projects')
        ->assertDontSee('New Dynamic Project');

    Project::factory()->create([
        'name' => 'New Dynamic Project',
        'owner_id' => $user->id,
    ]);

    $component->dispatch('project-created')
        ->assertSee('New Dynamic Project');
});

test('searching for percent wildcard returns only project with literal percent and not every project', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'Project 100%', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Other Project', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->set('search', '%')
        ->assertSee('Project 100%')
        ->assertDontSee('Other Project');
});

test('searching for underscore wildcard matches only a project whose name literally contains underscore', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'project_alpha', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'projectalpha', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->set('search', '_')
        ->assertSee('project_alpha')
        ->assertDontSee('projectalpha');
});

test('regression: searching for normal text continues to return matching projects', function () {
    $user = User::factory()->create();

    Project::factory()->create(['name' => 'Alpha Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Beta Project', 'owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->set('search', 'Alpha')
        ->assertSee('Alpha Project')
        ->assertDontSee('Beta Project');
});

test('never shows other users projects in the list or search results', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Project::factory()->create(['name' => 'My Project', 'owner_id' => $user->id]);
    Project::factory()->create(['name' => 'Foreign Project', 'owner_id' => $otherUser->id]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->assertSee('My Project')
        ->assertDontSee('Foreign Project')
        ->set('search', 'Foreign')
        ->assertDontSee('Foreign Project');
});

test('links each project card to its dashboard and omits due date when unset', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create([
        'name' => 'Undated Project',
        'due_at' => null,
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user);

    Livewire::test('projects.show-projects')
        ->assertSee('Undated Project')
        ->assertSeeHtml(route('projects.show', ['project' => $project]))
        ->assertDontSee('Due:');
});
