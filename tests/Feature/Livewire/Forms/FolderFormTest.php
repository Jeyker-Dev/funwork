<?php

use App\Models\Project;
use App\Models\User;
use App\Models\Folder;
use Livewire\Livewire;
use Livewire\Component;
use App\Livewire\Forms\FolderForm;

class FolderFormTestComponent extends Component
{
    public FolderForm $form;
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

test('it validates required name', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();

    $response = Livewire::test(FolderFormTestComponent::class, ['project' => $project])
        ->set('form.name', '')
        ->call('store');

    $response->assertHasErrors(['form.name' => 'required']);
});

test('it successfully creates a folder and resets form', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();

    $response = Livewire::test(FolderFormTestComponent::class, ['project' => $project])
        ->set('form.name', 'My New Folder')
        ->call('store');

    $response->assertHasNoErrors()
        ->assertSet('form.name', '');

    expect(Folder::count())->toBe(1);

    $folder = Folder::first();
    expect($folder->name)->toBe('My New Folder')
        ->and($folder->project_id)->toBe($project->id)
        ->and($folder->created_by)->toBe($user->id)
        ->and($folder->parent_id)->toBeNull();
});

test('it successfully creates a nested folder', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = Project::factory()->create();
    $parentFolder = Folder::factory()->create([
        'project_id' => $project->id,
        'created_by' => $user->id,
    ]);

    $response = Livewire::test(FolderFormTestComponent::class, ['project' => $project, 'currentFolderId' => $parentFolder->id])
        ->set('form.name', 'Nested Folder')
        ->call('store');

    $response->assertHasNoErrors();

    $folder = Folder::latest('id')->first();
    expect($folder->name)->toBe('Nested Folder')
        ->and($folder->parent_id)->toBe($parentFolder->id);
});
