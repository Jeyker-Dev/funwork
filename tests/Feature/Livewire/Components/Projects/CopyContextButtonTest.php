<?php

use App\Actions\Projects\BuildProjectContextAction;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('project header renders the copy context for AI button', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)
        ->get(route('projects.show', $project))
        ->assertSuccessful()
        ->assertSee('Copy context for AI')
        ->assertSeeHtml('data-test="copy-context-button"');
});

test('copyContext returns the project markdown brief for the owner', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Context Export Project',
    ]);
    Task::factory()->create([
        'project_id' => $project->id,
        'title' => 'Document context export',
        'status' => TaskStatus::Todo,
    ]);

    $expectedMarkdown = app(BuildProjectContextAction::class)($project, $owner);

    Livewire::actingAs($owner)
        ->test('projects.copy-context-button', ['project' => $project])
        ->call('copyContext')
        ->assertHasNoErrors()
        ->assertReturned($expectedMarkdown);
});

test('copyContext is forbidden for another user', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    Livewire::actingAs($intruder)
        ->test('projects.copy-context-button', ['project' => $project])
        ->assertForbidden();

    $component = Livewire::actingAs($owner)
        ->test('projects.copy-context-button', ['project' => $project]);

    $project->update(['owner_id' => $intruder->id]);

    $component->call('copyContext')
        ->assertForbidden();
});
