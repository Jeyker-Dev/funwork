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
        ->assertSee('John Doe')
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
