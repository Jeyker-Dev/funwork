<?php

use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_id' => $this->user->id,
    ]);
});

it('splits the extension on mount and saves correctly while preserving the extension for a file', function () {
    $folder = Folder::factory()->create(['project_id' => $this->project->id]);
    $file = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'folder_id' => $folder->id,
        'name' => 'document.pdf',
    ]);

    Livewire::actingAs($this->user)
        ->test('projects.files.⚡rename-item-modal')
        ->call('mountFile', $file->id)
        ->assertSet('form.name', 'document')
        ->assertSet('form.extension', '.pdf')
        ->set('form.name', 'new_document')
        ->call('save')
        ->assertDispatched('item-renamed')
        ->assertHasNoErrors();

    expect($file->fresh()->name)->toBe('new_document.pdf');
});

it('operates correctly without extensions for a folder', function () {
    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'name' => 'old_folder',
    ]);

    Livewire::actingAs($this->user)
        ->test('projects.files.⚡rename-item-modal')
        ->call('mountFolder', $folder->id)
        ->assertSet('form.name', 'old_folder')
        ->assertSet('form.extension', '')
        ->set('form.name', 'new_folder')
        ->call('save')
        ->assertDispatched('item-renamed')
        ->assertHasNoErrors();

    expect($folder->fresh()->name)->toBe('new_folder');
});

it('fails uniqueness validation if a file or folder with the target name already exists in the same directory', function () {
    $folder = Folder::factory()->create(['project_id' => $this->project->id]);

    $file1 = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'folder_id' => $folder->id,
        'name' => 'report.pdf',
    ]);

    $file2 = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'folder_id' => $folder->id,
        'name' => 'other.pdf',
    ]);

    $subFolder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'parent_id' => $folder->id,
        'name' => 'report.pdf', // Folder named like the file
    ]);

    Livewire::actingAs($this->user)
        ->test('projects.files.⚡rename-item-modal')
        ->call('mountFile', $file2->id)
        ->set('form.name', 'report') // With .pdf extension, it will be report.pdf
        ->call('save')
        ->assertHasErrors(['form.name' => 'The name has already been taken.']);

    // Also test renaming to a folder name
    $folder2 = Folder::factory()->create([
        'project_id' => $this->project->id,
        'parent_id' => $folder->id,
        'name' => 'other_folder',
    ]);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'parent_id' => $folder->id,
        'name' => 'existing_folder',
    ]);

    Livewire::actingAs($this->user)
        ->test('projects.files.⚡rename-item-modal')
        ->call('mountFolder', $folder2->id)
        ->set('form.name', 'existing_folder')
        ->call('save')
        ->assertHasErrors(['form.name' => 'The name has already been taken.']);
});

it('returns 403 when an unauthorized user attempts to mount or submit', function () {
    $otherUser = User::factory()->create(); // Not attached to project

    $folder = Folder::factory()->create(['project_id' => $this->project->id]);
    $file = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'folder_id' => $folder->id,
        'name' => 'document.pdf',
    ]);

    Livewire::actingAs($otherUser)
        ->test('projects.files.⚡rename-item-modal')
        ->call('mountFile', $file->id)
        ->assertForbidden();
});
