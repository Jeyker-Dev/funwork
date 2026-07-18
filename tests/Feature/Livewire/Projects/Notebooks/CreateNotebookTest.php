<?php

use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('create notebook component renders correctly', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    $component = Livewire::test('projects.notebooks.⚡create-notebook', ['project' => $project]);

    $component->assertSee('Create Notebook')
        ->assertSee('Fill out the details below');
});
