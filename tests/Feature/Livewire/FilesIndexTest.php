<?php

use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
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
        ->call('downloadFolder', $folder->id)
        ->assertSet('selectedIds', [])
        ->assertFileDownloaded();
});

it('downloads a folder as a ZIP containing files from a subfolder', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $parentFolder = Folder::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'name' => 'Parent',
    ]);

    $subFolder = Folder::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
        'parent_id' => $parentFolder->id,
        'name' => 'Sub',
    ]);

    $file = ProjectFile::factory()->create([
        'project_id' => $project->id,
        'folder_id' => $subFolder->id,
        'created_by' => $user->id,
        'name' => 'nested.txt',
        'disk' => 'local',
        'file_path' => 'projects/'.$project->id.'/nested.txt',
    ]);
    Storage::disk('local')->put($file->file_path, 'nested content');

    $this->actingAs($user);

    $component = Livewire::test('pages::projects.files.index', ['project' => $project])
        ->call('downloadFolder', $parentFolder->id)
        ->assertFileDownloaded();

    $content = base64_decode(data_get($component->effects, 'download.content'));
    $tempFile = tempnam(sys_get_temp_dir(), 'zip_test_');
    file_put_contents($tempFile, $content);

    $zip = new ZipArchive;
    expect($zip->open($tempFile))->toBeTrue();
    expect($zip->getFromName('Parent/Sub/nested.txt'))->toBe('nested content');
    $zip->close();
    unlink($tempFile);
});

it('forbids another user from accessing and downloading project folders', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $folder = Folder::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($otherUser);

    Livewire::test('pages::projects.files.index', ['project' => $project])
        ->assertForbidden();
});
