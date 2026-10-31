<?php

use App\Livewire\Forms\UploadFileForm;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\WithFileUploads;

class UploadFileFormTestComponent extends Component
{
    use WithFileUploads;

    public UploadFileForm $form;

    public ?Project $project = null;

    public function mount(Project $project, ?int $currentFolderId = null)
    {
        $this->project = $project;
        $this->form->setContext($project, $currentFolderId);
    }

    public function store()
    {
        $this->form->store();
    }

    public function render()
    {
        return '<div></div>';
    }
}

test('it validates required files', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create(['owner_id' => $user->id]);

    $response = Livewire::test(UploadFileFormTestComponent::class, ['project' => $project])
        ->set('form.files', [])
        ->call('store');

    $response->assertHasErrors(['form.files']);
});

test('it successfully uploads files and resets form', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create(['owner_id' => $user->id]);

    $file1 = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
    $file2 = UploadedFile::fake()->create('image.png', 512, 'image/png');

    $response = Livewire::test(UploadFileFormTestComponent::class, ['project' => $project])
        ->set('form.files', [$file1, $file2])
        ->call('store');

    $response->assertHasNoErrors()
        ->assertSet('form.files', []);

    expect(ProjectFile::count())->toBe(2);

    $files = ProjectFile::all();

    expect($files[0]->name)->toBe('document.pdf')
        ->and($files[0]->mime_type)->toBe('application/pdf')
        ->and($files[0]->size)->toBe(1048576)
        ->and($files[0]->project_id)->toBe($project->id)
        ->and($files[0]->created_by)->toBe($user->id)
        ->and($files[0]->folder_id)->toBeNull();

    expect($files[1]->name)->toBe('image.png')
        ->and($files[1]->mime_type)->toBe('image/png')
        ->and($files[1]->project_id)->toBe($project->id);

    Storage::disk('s3')->assertExists($files[0]->file_path);
    Storage::disk('s3')->assertExists($files[1]->file_path);
});

test('it successfully uploads files to a specific folder', function () {
    Storage::fake('s3');

    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create(['owner_id' => $user->id]);
    $folder = Folder::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

    $response = Livewire::test(UploadFileFormTestComponent::class, ['project' => $project, 'currentFolderId' => $folder->id])
        ->set('form.files', [$file])
        ->call('store');

    $response->assertHasNoErrors();

    $projectFile = ProjectFile::first();
    expect($projectFile->name)->toBe('test.txt')
        ->and($projectFile->folder_id)->toBe($folder->id);
});
