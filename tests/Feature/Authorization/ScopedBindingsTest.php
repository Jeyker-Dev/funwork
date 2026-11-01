<?php

use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->projectA = Project::factory()->create(['owner_id' => $this->user->id]);
    $this->projectB = Project::factory()->create(['owner_id' => $this->user->id]);

    $this->actingAs($this->user);
});

function createPage(Notebook $notebook, User $user): NotebookPage
{
    return NotebookPage::create([
        'notebook_id' => $notebook->id,
        'content' => ['title' => 'Page', 'body' => ''],
        'order' => 1,
        'created_by' => $user->id,
    ]);
}

test('a task from another project returns 404', function () {
    $taskB = Task::factory()->create(['project_id' => $this->projectB->id]);

    $this->get(route('show.task', [$this->projectA, $taskB]))->assertNotFound();
});

test('a file from another project returns 404', function () {
    $fileB = ProjectFile::factory()->create([
        'project_id' => $this->projectB->id,
        'created_by' => $this->user->id,
    ]);

    $this->get(route('project.files.preview', [$this->projectA, $fileB]))->assertNotFound();
});

test('a notebook from another project returns 404', function () {
    $notebookB = Notebook::factory()->create([
        'project_id' => $this->projectB->id,
        'created_by' => $this->user->id,
    ]);
    $pageB = createPage($notebookB, $this->user);

    $this->get(route('project.notebooks.pages.edit', [$this->projectA, $notebookB, $pageB]))->assertNotFound();
});

test('a page from another notebook returns 404', function () {
    $notebookA = Notebook::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->user->id,
    ]);
    $otherNotebookA = Notebook::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->user->id,
    ]);
    $otherPage = createPage($otherNotebookA, $this->user);

    $this->get(route('project.notebooks.pages.edit', [$this->projectA, $notebookA, $otherPage]))->assertNotFound();
});

test('matching nested resources still resolve', function () {
    $taskA = Task::factory()->create(['project_id' => $this->projectA->id]);
    $notebookA = Notebook::factory()->create([
        'project_id' => $this->projectA->id,
        'created_by' => $this->user->id,
    ]);
    $pageA = createPage($notebookA, $this->user);

    $this->get(route('show.task', [$this->projectA, $taskA]))->assertOk();
    $this->get(route('project.notebooks.pages.edit', [$this->projectA, $notebookA, $pageA]))->assertOk();
});
