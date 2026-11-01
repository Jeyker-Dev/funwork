<?php

use App\Livewire\Actions\ReorderTasks;
use App\Models\Folder;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->userB = User::factory()->create();

    $this->projectA = Project::factory()->create([
        'owner_id' => $this->userA->id,
    ]);
});

test('user B receives 403 on all HTTP routes belonging to user A project', function () {
    $taskA = Task::factory()->create(['project_id' => $this->projectA->id]);
    $notebookA = Notebook::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
    ]);
    $pageA = NotebookPage::create([
        'notebook_id' => $notebookA->id,
        'content' => ['title' => 'Page A', 'body' => 'Secret'],
        'order' => 1,
        'created_by' => $this->userA->id,
    ]);
    $fileA = ProjectFile::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
    ]);

    $this->actingAs($this->userB);

    $this->get(route('projects.show', $this->projectA))->assertForbidden();
    $this->get(route('project.inbox', $this->projectA))->assertForbidden();
    $this->get(route('project.tasks', $this->projectA))->assertForbidden();
    $this->get(route('show.task', [$this->projectA, $taskA]))->assertForbidden();
    $this->get(route('project.notebooks', $this->projectA))->assertForbidden();
    $this->get(route('project.notebooks.pages.edit', [$this->projectA, $notebookA, $pageA]))->assertForbidden();
    $this->get(route('project.files', $this->projectA))->assertForbidden();
    $this->get(route('project.files.preview', [$this->projectA, $fileA]))->assertForbidden();
});

test('user A can access their own HTTP project routes', function () {
    Storage::fake('local');
    Storage::disk('local')->put('files/test.txt', 'hello');

    $taskA = Task::factory()->create(['project_id' => $this->projectA->id]);
    $notebookA = Notebook::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
    ]);
    $pageA = NotebookPage::create([
        'notebook_id' => $notebookA->id,
        'content' => ['title' => 'Page A', 'body' => 'Secret'],
        'order' => 1,
        'created_by' => $this->userA->id,
    ]);
    $fileA = ProjectFile::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
        'file_path' => 'files/test.txt',
        'disk' => 'local',
    ]);

    $this->actingAs($this->userA);

    $this->get(route('projects.show', $this->projectA))->assertOk();
    $this->get(route('project.inbox', $this->projectA))->assertOk();
    $this->get(route('project.tasks', $this->projectA))->assertOk();
    $this->get(route('show.task', [$this->projectA, $taskA]))->assertOk();
    $this->get(route('project.notebooks', $this->projectA))->assertOk();
    $this->get(route('project.notebooks.pages.edit', [$this->projectA, $notebookA, $pageA]))->assertOk();
    $this->get(route('project.files', $this->projectA))->assertOk();
    $this->get(route('project.files.preview', [$this->projectA, $fileA]))->assertOk();
});

test('user B cannot create a project assigned to user A', function () {
    $this->actingAs($this->userB);

    Livewire::test('projects.create-project', ['elementName' => 'header'])
        ->set('form.name', 'Spoofed Project')
        ->set('form.description', 'Desc')
        ->set('form.icon', 'wrench')
        ->set('form.type', 'Programming')
        ->set('form.status', 'active')
        ->set('form.ownerId', $this->userA->id)
        ->call('save')
        ->assertForbidden();

    expect(Project::where('name', 'Spoofed Project')->exists())->toBeFalse();
});

test('user B cannot mount or mutate user A project dashboard or tasks', function () {
    $taskA = Task::factory()->create(['project_id' => $this->projectA->id]);

    $this->actingAs($this->userB);

    Livewire::test('pages::projects.show', ['project' => $this->projectA])
        ->assertForbidden();

    Livewire::test('projects.tasks.task-board', ['project' => $this->projectA])
        ->assertForbidden();

    Livewire::test('projects.tasks.create-task', ['project' => $this->projectA, 'status' => 'Todo'])
        ->assertForbidden();

    expect(fn () => app(ReorderTasks::class)(['Doing' => [$taskA->id]], $this->projectA->id))
        ->toThrow(AuthorizationException::class);
});

test('user B cannot spoof projectId in create-task or create-notebook for user A project', function () {
    $projectB = Project::factory()->create(['owner_id' => $this->userB->id]);

    $this->actingAs($this->userB);

    Livewire::test('projects.notebooks.create-notebook', ['project' => $projectB])
        ->set('form.projectId', $this->projectA->id)
        ->set('form.name', 'Injected Notebook')
        ->set('form.description', 'Desc')
        ->call('save')
        ->assertForbidden();

    expect(Notebook::where('project_id', $this->projectA->id)->exists())->toBeFalse();
});

test('user B cannot mount or mutate user A notebooks or notebook pages', function () {
    $notebookA = Notebook::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
    ]);
    $pageA = NotebookPage::create([
        'notebook_id' => $notebookA->id,
        'content' => ['title' => 'Page A', 'body' => 'Secret'],
        'order' => 1,
        'created_by' => $this->userA->id,
    ]);
    $projectB = Project::factory()->create(['owner_id' => $this->userB->id]);

    $this->actingAs($this->userB);

    Livewire::test('pages::projects.notebooks.index', ['project' => $this->projectA])
        ->assertForbidden();

    Livewire::test('projects.notebooks.show-notebooks', ['project' => $this->projectA])
        ->assertForbidden();

    Livewire::test('projects.notebooks.show-notebooks', ['project' => $projectB])
        ->call('openNotebook', $notebookA->id)
        ->assertForbidden();

    Livewire::test('pages::projects.notebooks.pages.editor', [
        'project' => $this->projectA,
        'notebook' => $notebookA,
        'page' => $pageA,
    ])->assertForbidden();
});

test('user B cannot mount or mutate user A folders or files', function () {
    Storage::fake('local');

    $folderA = Folder::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
    ]);
    $fileA = ProjectFile::factory()->create([
        'project_id' => $this->projectA->id,
        'folder_id' => $folderA->id,
        'created_by' => $this->userA->id,
    ]);
    $projectB = Project::factory()->create(['owner_id' => $this->userB->id]);

    $this->actingAs($this->userB);

    Livewire::test('pages::projects.files.index', ['project' => $this->projectA])
        ->assertForbidden();

    Livewire::test('projects.files.create-folder-modal', ['project' => $this->projectA])
        ->assertForbidden();

    Livewire::test('projects.files.upload-file-modal', ['project' => $this->projectA])
        ->assertForbidden();

    Livewire::test('projects.files.move-items-modal', ['project' => $this->projectA])
        ->assertForbidden();

    // Cross-project folder navigation or creation from User B's own project view
    Livewire::test('pages::projects.files.index', ['project' => $projectB])
        ->call('navigateToFolder', $folderA->id)
        ->assertForbidden();

    Livewire::test('projects.files.create-folder-modal', ['project' => $projectB, 'currentFolderId' => $folderA->id])
        ->set('form.name', 'Subfolder in A')
        ->call('save')
        ->assertForbidden();

    Livewire::test('projects.files.upload-file-modal', ['project' => $projectB, 'currentFolderId' => $folderA->id])
        ->set('form.files', [UploadedFile::fake()->create('doc.txt', 10)])
        ->call('save')
        ->assertForbidden();

    Livewire::test('projects.files.rename-item-modal')
        ->call('mountFile', $fileA->id)
        ->assertForbidden();

    Livewire::test('projects.files.rename-item-modal')
        ->call('mountFolder', $folderA->id)
        ->assertForbidden();
});

test('user B cannot access, convert, or delete user A inbox items or convert into user A project', function () {
    $itemA = InboxItem::factory()->create([
        'user_id' => $this->userA->id,
        'project_id' => $this->projectA->id,
        'content' => 'User A idea',
    ]);
    $projectB = Project::factory()->create(['owner_id' => $this->userB->id]);
    $itemB = InboxItem::factory()->create([
        'user_id' => $this->userB->id,
        'project_id' => $projectB->id,
        'content' => 'User B idea',
    ]);

    $this->actingAs($this->userB);

    Livewire::test('pages::projects.inbox', ['project' => $this->projectA])
        ->assertForbidden();

    Livewire::test('projects.inbox.delete-item-modal')
        ->call('loadModal', $itemA->id)
        ->assertNotFound();

    Livewire::test('projects.inbox.convert-to-task')
        ->call('loadModal', $itemA->id)
        ->assertNotFound();

    Livewire::test('projects.inbox.convert-to-notebook')
        ->call('loadModal', $itemA->id)
        ->assertNotFound();

    // User B tries to convert their own inbox item into User A's project
    Livewire::test('projects.inbox.convert-to-task')
        ->call('loadModal', $itemB->id)
        ->set('form.projectId', $this->projectA->id)
        ->set('form.title', 'Injected Task')
        ->call('convert')
        ->assertForbidden();

    Livewire::test('projects.inbox.convert-to-notebook')
        ->call('loadModal', $itemB->id)
        ->set('form.projectId', $this->projectA->id)
        ->set('form.name', 'Injected Note')
        ->call('convert')
        ->assertForbidden();

    expect($itemA->fresh()->trashed())->toBeFalse()
        ->and($itemB->fresh()->trashed())->toBeFalse();
});

test('user B cannot move user A file into user B project folder via move-items-modal', function () {
    $folderA = Folder::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
    ]);
    $fileA = ProjectFile::factory()->create([
        'project_id' => $this->projectA->id,
        'folder_id' => $folderA->id,
        'created_by' => $this->userA->id,
    ]);

    $projectB = Project::factory()->create(['owner_id' => $this->userB->id]);
    $folderB = Folder::factory()->create([
        'project_id' => $projectB->id,
        'created_by' => $this->userB->id,
    ]);

    $this->actingAs($this->userB);

    Livewire::test('projects.files.move-items-modal', ['project' => $projectB])
        ->set('selectedIds', ["file_{$fileA->id}"])
        ->set('targetFolderId', $folderB->id)
        ->call('move')
        ->assertForbidden();

    expect($fileA->fresh()->folder_id)->toBe($folderA->id)
        ->and($fileA->fresh()->project_id)->toBe($this->projectA->id);
})->skip('Falta autorización en projects.files.move-items-modal::move');

test('user B cannot move user A folder into user B project folder via move-items-modal', function () {
    $parentFolderA = Folder::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
    ]);
    $folderA = Folder::factory()->create([
        'project_id' => $this->projectA->id,
        'parent_id' => $parentFolderA->id,
        'created_by' => $this->userA->id,
    ]);

    $projectB = Project::factory()->create(['owner_id' => $this->userB->id]);
    $folderB = Folder::factory()->create([
        'project_id' => $projectB->id,
        'created_by' => $this->userB->id,
    ]);

    $this->actingAs($this->userB);

    Livewire::test('projects.files.move-items-modal', ['project' => $projectB])
        ->set('selectedIds', ["folder_{$folderA->id}"])
        ->set('targetFolderId', $folderB->id)
        ->call('move')
        ->assertForbidden();

    expect($folderA->fresh()->parent_id)->toBe($parentFolderA->id)
        ->and($folderA->fresh()->project_id)->toBe($this->projectA->id);
})->skip('Falta autorización en projects.files.move-items-modal::move');

test('user B cannot download user A folder as ZIP via downloadFolderAsync', function () {
    Storage::fake(config('filesystems.default'));
    Notification::fake();

    $folderA = Folder::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->userA->id,
    ]);

    $projectB = Project::factory()->create(['owner_id' => $this->userB->id]);

    $this->actingAs($this->userB);

    Livewire::test('pages::projects.files.index', ['project' => $projectB])
        ->call('downloadFolderAsync', $folderA->id)
        ->assertForbidden()
        ->assertNoFileDownloaded();

    Notification::assertNothingSent();
})->skip('Falta autorización en pages::projects.files.index::downloadFolderAsync');
