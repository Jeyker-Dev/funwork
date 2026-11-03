<?php

use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

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

    Livewire::test('projects.files.⚡create-folder-modal', ['project' => $this->project])
        ->set('form.name', 'New Folder')
        ->call('save')
        ->assertHasNoErrors();

    expect(Folder::where('name', 'New Folder')->where('project_id', $this->project->id)->exists())->toBeTrue();
});

test('it can upload files', function () {
    $this->actingAs($this->user);
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $file = UploadedFile::fake()->image('test.jpg');

    Livewire::test('projects.files.⚡upload-file-modal', ['project' => $this->project])
        ->set('form.files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    expect(ProjectFile::where('name', 'test.jpg')->where('project_id', $this->project->id)->exists())->toBeTrue();

    $uploadedFile = ProjectFile::where('name', 'test.jpg')->first();
    Storage::disk($disk)->assertExists($uploadedFile->file_path);
});

test('it can download selected items', function () {
    $this->actingAs($this->user);
    Storage::fake('s3');
    Storage::fake('local'); // needed for zip if applicable

    $file = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'disk' => 'local',
        'file_path' => 'projects/'.$this->project->id.'/test.txt',
    ]);

    Storage::disk('local')->put($file->file_path, 'test content');

    $response = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['file_'.$file->id])
        ->call('downloadSelected');

    $response->assertHasNoErrors();
    $response->assertFileDownloaded();
});

test('it can delete selected items', function () {
    $this->actingAs($this->user);

    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'To Be Deleted',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['folder_'.$folder->id])
        ->call('deleteSelected')
        ->assertHasNoErrors()
        ->assertSet('selectedIds', []);

    // Verify it was soft deleted
    expect(Folder::withTrashed()->find($folder->id)->trashed())->toBeTrue();
});

test('it triggers move modal with selected items', function () {
    $this->actingAs($this->user);
    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Folder to move',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['folder_'.$folder->id])
        ->call('moveSelected')
        ->assertDispatched('open-move-modal', selectedIds: ['folder_'.$folder->id]);
});

test('it clears selection on items moved event', function () {
    $this->actingAs($this->user);
    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Folder to clear',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['folder_'.$folder->id])
        ->dispatch('items-moved')
        ->assertSet('selectedIds', []);
});

test('searching for percent wildcard returns only file with literal percent in name', function () {
    $this->actingAs($this->user);

    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'documento.pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'descuento_10%.pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', '%')
        ->assertSee('descuento_10%.pdf')
        ->assertDontSee('documento.pdf');
});

test('searching for underscore wildcard returns only test_file.pdf and not testfile.pdf', function () {
    $this->actingAs($this->user);

    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'test_file.pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'testfile.pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', '_')
        ->assertSee('test_file.pdf')
        ->assertDontSee('testfile.pdf');
});

test('searching for underscore wildcard also filters folders with and without underscore', function () {
    $this->actingAs($this->user);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'folder_one',
    ]);
    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'folderone',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', '_')
        ->assertSee('folder_one')
        ->assertDontSee('folderone');
});

test('regression: searching for normal text continues to return matching files and folders', function () {
    $this->actingAs($this->user);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'reportes_anuales',
    ]);
    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'otra_carpeta',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'reporte_mensual.pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'documento.pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', 'reporte')
        ->assertSee('reportes_anuales')
        ->assertSee('reporte_mensual.pdf')
        ->assertDontSee('otra_carpeta')
        ->assertDontSee('documento.pdf');
});

test('searching for exclamation mark does not break query and returns only items with exclamation mark', function () {
    $this->actingAs($this->user);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'urgente!',
    ]);
    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'general',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'importante!.pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'normal.pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', '!')
        ->assertSee('urgente!')
        ->assertSee('importante!.pdf')
        ->assertDontSee('general')
        ->assertDontSee('normal.pdf');
});
