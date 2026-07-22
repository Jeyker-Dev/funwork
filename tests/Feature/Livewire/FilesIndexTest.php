<?php

use App\Models\Folder;
use App\Models\Project;
use App\Models\User;
use Livewire\Livewire;

it('clears bulk selection and sets single selection when triggering move action', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.files.index', ['project' => $project])
        ->set('selectedIds', ['file_1', 'file_2', 'folder_1'])
        ->call('setSingleSelectionAndMove', 'file_3')
        ->assertSet('selectedIds', ['file_3'])
        ->assertDispatched('open-move-modal', selectedIds: ['file_3']);
});

it('clears bulk selection and sets single selection when triggering delete action', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.files.index', ['project' => $project])
        ->set('selectedIds', ['file_1', 'file_2'])
        ->call('setSingleSelectionAndDelete', 'folder_2')
        ->assertSet('selectedIds', ['folder_2']);
});

it('triggers folder download synchronously and clears selection', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $folder = Folder::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $this->actingAs($user);

    Livewire::test('pages::projects.files.index', ['project' => $project])
        ->set('selectedIds', ['file_1', 'file_2'])
        ->call('downloadFolderAsync', $folder->id)
        ->assertSet('selectedIds', [])
        ->assertFileDownloaded();
});
