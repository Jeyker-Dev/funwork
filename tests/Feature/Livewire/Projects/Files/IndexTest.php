<?php

use App\Models\Project;
use App\Models\User;
use App\Models\ProjectFile;
use App\Models\Folder;
use Livewire\Livewire;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_id' => $this->user->id,
    ]);
});

test('it renders successfully', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->assertOk()
        ->assertSee('Files');
});

test('it can create a folder', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('folderForm.name', 'New Folder')
        ->call('createFolder')
        ->assertHasNoErrors();

    expect(Folder::where('name', 'New Folder')->where('project_id', $this->project->id)->exists())->toBeTrue();
});

test('it can upload files', function () {
    $this->actingAs($this->user);
    Storage::fake('s3');

    $file = UploadedFile::fake()->image('test.jpg');

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('uploadForm.files', [$file])
        ->call('uploadFiles')
        ->assertHasNoErrors();

    expect(ProjectFile::where('name', 'test.jpg')->where('project_id', $this->project->id)->exists())->toBeTrue();
    
    $uploadedFile = ProjectFile::where('name', 'test.jpg')->first();
    Storage::disk('s3')->assertExists($uploadedFile->file_path);
});

test('it can download selected items', function () {
    $this->actingAs($this->user);
    Storage::fake('s3');
    Storage::fake('local'); // needed for zip if applicable

    $file = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'disk' => 'local',
        'file_path' => 'projects/' . $this->project->id . '/test.txt',
    ]);
    
    Storage::disk('local')->put($file->file_path, 'test content');

    $response = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['file_' . $file->id])
        ->call('downloadSelected');

    $response->assertHasNoErrors();
    $response->assertFileDownloaded();
});

test('it can delete selected items', function () {
    $this->actingAs($this->user);

    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'To Be Deleted'
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['folder_' . $folder->id])
        ->call('deleteSelected')
        ->assertHasNoErrors()
        ->assertSet('selectedIds', []);

    // Verify it was soft deleted
    expect(Folder::withTrashed()->find($folder->id)->trashed())->toBeTrue();
});
