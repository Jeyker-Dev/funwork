<?php

use App\Models\Notebook;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

test('it validates required project_id', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();

    $response = Livewire::test('projects.notebooks.⚡create-notebook', ['project' => $project])
        ->set('form.projectId', null)
        ->set('form.name', 'My Notebook')
        ->set('form.description', 'Notebook desc')
        ->call('save');

    $response->assertHasErrors(['form.projectId']);
});

test('it validates required created_by', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();

    $response = Livewire::test('projects.notebooks.⚡create-notebook', ['project' => $project])
        ->set('form.createdBy', null)
        ->set('form.name', 'My Notebook')
        ->set('form.description', 'Notebook desc')
        ->call('save');

    $response->assertHasErrors(['form.createdBy']);
});

test('it successfully persists notebook, dispatches event and resets form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();

    $response = Livewire::test('projects.notebooks.⚡create-notebook', ['project' => $project])
        ->set('form.name', 'My Notebook')
        ->set('form.description', 'Notebook desc')
        ->call('save');

    $response->assertHasNoErrors()
        ->assertDispatched('created-notebook')
        ->assertSet('form.name', '')
        ->assertSet('form.description', '');

    expect(Notebook::count())->toBe(1);

    $notebook = Notebook::first();
    expect($notebook->name)->toBe('My Notebook')
        ->and($notebook->project_id)->toBe($project->id)
        ->and($notebook->created_by)->toBe($user->id);
});
