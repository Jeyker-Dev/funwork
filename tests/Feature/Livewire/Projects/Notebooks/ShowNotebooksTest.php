<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Notebook;
use Livewire\Livewire;

test('show notebooks component renders list correctly', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'First Notebook',
        'description' => 'A great notebook',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('projects.notebooks.⚡show-notebooks', ['project' => $project]);

    $component->assertSee('First Notebook')
              ->assertSee('A great notebook');
});

test('asserts empty state rendering ("No Notebooks, Yet.") when the project has zero notebooks on index', function () {
    // This satisfies task 4.3 which specifically requested it here. 
    // We'll test the index component since that's where the empty state lives.
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.notebooks.index', ['project' => $project]);

    $component->assertSee('No Notebooks,')
              ->assertSee('Yet.');
});
