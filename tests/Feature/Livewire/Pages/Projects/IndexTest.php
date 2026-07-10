<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('empty state rendering shows text and create-project component', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.index');

    $component
        ->assertSee('Here you can manage all your projects')
        ->assertSeeLivewire('projects.create-project');
});

test('populated state rendering shows prompt and show-projects component', function () {
    $user = User::factory()->create();
    
    Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.index');

    $component
        ->assertSee('Pick a project to continue working on it')
        ->assertSeeLivewire('projects.show-projects');
});

test('reacts to project-created event by refreshing data', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.index');

    $component->assertViewHas('projects', function ($projects) {
        return $projects->count() === 0;
    });

    Project::factory()->create([
        'owner_id' => $user->id,
    ]);

    $component->dispatch('project-created');

    $component->assertViewHas('projects', function ($projects) {
        return $projects->count() === 1;
    });
});
