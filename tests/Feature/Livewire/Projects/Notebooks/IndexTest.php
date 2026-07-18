<?php

use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('index component mounts successfully and shows empty state', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.notebooks.index', ['project' => $project]);

    $component->assertSee('No Notebooks,')
        ->assertSee('Yet.');
});

test('index component shows populated state when notebooks exist', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Existing Notebook',
    ]);

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.notebooks.index', ['project' => $project]);

    $component->assertSee('All Notebook')
        ->assertSeeLivewire('projects.notebooks.show-notebooks');
});
