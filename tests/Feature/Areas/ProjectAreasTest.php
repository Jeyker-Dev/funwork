<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Livewire\Livewire;

test('creating a project through the create-project component creates 4 default areas in order', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('projects.create-project', ['elementName' => ''])
        ->set('form.name', 'New Project')
        ->set('form.description', 'Project with default areas')
        ->set('form.icon', 'rocket')
        ->set('form.type', 'software')
        ->set('form.status', 'active')
        ->set('form.ownerId', $user->id)
        ->call('save')
        ->assertHasNoErrors();

    $project = Project::where('name', 'New Project')->first();
    expect($project)->not->toBeNull();

    $areas = $project->areas()->orderBy('position')->get();
    expect($areas)->toHaveCount(4);

    expect($areas[0]->name)->toBe('Frontend')
        ->and($areas[0]->slug)->toBe('frontend')
        ->and($areas[0]->color)->toBe('blue')
        ->and($areas[0]->position)->toBe(1);

    expect($areas[1]->name)->toBe('Backend')
        ->and($areas[1]->slug)->toBe('backend')
        ->and($areas[1]->color)->toBe('green')
        ->and($areas[1]->position)->toBe(2);

    expect($areas[2]->name)->toBe('Database')
        ->and($areas[2]->slug)->toBe('database')
        ->and($areas[2]->color)->toBe('amber')
        ->and($areas[2]->position)->toBe(3);

    expect($areas[3]->name)->toBe('Design')
        ->and($areas[3]->slug)->toBe('design')
        ->and($areas[3]->color)->toBe('purple')
        ->and($areas[3]->position)->toBe(4);
});

test('areaByKey matches slug case-insensitively and returns null for unknown key', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    expect($project->areaByKey('BACKEND'))->not->toBeNull()
        ->and($project->areaByKey('BACKEND')->slug)->toBe('backend')
        ->and($project->areaByKey('Backend')->slug)->toBe('backend')
        ->and($project->areaByKey('backend')->slug)->toBe('backend')
        ->and($project->areaByKey('FRONTEND')->slug)->toBe('frontend')
        ->and($project->areaByKey('unknown'))->toBeNull();
});

test('the owner can create, rename, recolor, reorder and delete areas', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    $component = Livewire::actingAs($user)
        ->test('projects.⚡manage-areas', ['project' => $project]);

    // Create a new area
    $component->set('newName', 'DevOps')
        ->set('newColor', 'cyan')
        ->call('createArea')
        ->assertHasNoErrors();

    $devops = $project->areas()->where('slug', 'devops')->first();
    expect($devops)->not->toBeNull()
        ->and($devops->name)->toBe('DevOps')
        ->and($devops->color)->toBe('cyan')
        ->and($devops->position)->toBe(5);

    // Duplicate slug in same project is rejected
    $component->set('newName', 'DevOps')
        ->set('newColor', 'red')
        ->call('createArea')
        ->assertHasErrors(['newName']);

    // Rename and recolor
    $component->call('startEditing', $devops->id)
        ->set('editingName', 'Infrastructure')
        ->set('editingColor', 'pink')
        ->call('updateArea')
        ->assertHasNoErrors();

    $devops->refresh();
    expect($devops->name)->toBe('Infrastructure')
        ->and($devops->slug)->toBe('infrastructure')
        ->and($devops->color)->toBe('pink');

    // Move up
    $component->call('moveUp', $devops->id);
    $devops->refresh();
    expect($devops->position)->toBe(4);

    // Move down
    $component->call('moveDown', $devops->id);
    $devops->refresh();
    expect($devops->position)->toBe(5);

    // Delete area
    $component->call('confirmDelete', $devops->id)
        ->call('deleteArea')
        ->assertHasNoErrors();

    expect($project->areas()->where('id', $devops->id)->exists())->toBeFalse();
});

test('duplicate slug in another project is allowed', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $userA->id]);
    $projectB = Project::factory()->create(['owner_id' => $userB->id]);

    app(CreateDefaultAreasAction::class)($projectA);
    app(CreateDefaultAreasAction::class)($projectB);

    Livewire::actingAs($userA)
        ->test('projects.⚡manage-areas', ['project' => $projectA])
        ->set('newName', 'QA')
        ->set('newColor', 'zinc')
        ->call('createArea')
        ->assertHasNoErrors();

    Livewire::actingAs($userB)
        ->test('projects.⚡manage-areas', ['project' => $projectB])
        ->set('newName', 'QA')
        ->set('newColor', 'zinc')
        ->call('createArea')
        ->assertHasNoErrors();

    expect($projectA->areas()->where('slug', 'qa')->exists())->toBeTrue()
        ->and($projectB->areas()->where('slug', 'qa')->exists())->toBeTrue();
});

test('another user is denied from managing areas', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    Livewire::actingAs($other)
        ->test('projects.⚡manage-areas', ['project' => $project])
        ->assertForbidden();
});

test('deleting an area sets area_id to null on its tasks and notebooks without deleting them', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    app(CreateDefaultAreasAction::class)($project);

    $area = $project->areas()->where('slug', 'backend')->first();

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
        'title' => 'Backend Task',
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'area_id' => $area->id,
        'name' => 'Backend Notebook',
    ]);

    Livewire::actingAs($user)
        ->test('projects.⚡manage-areas', ['project' => $project])
        ->call('confirmDelete', $area->id)
        ->call('deleteArea')
        ->assertHasNoErrors();

    expect($project->areas()->where('id', $area->id)->exists())->toBeFalse();

    $task->refresh();
    $notebook->refresh();

    expect($task->area_id)->toBeNull()
        ->and($notebook->area_id)->toBeNull()
        ->and(Task::where('id', $task->id)->exists())->toBeTrue()
        ->and(Notebook::where('id', $notebook->id)->exists())->toBeTrue();
});
