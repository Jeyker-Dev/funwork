<?php

use App\Actions\Notebooks\DeleteNotebookPageAction;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it falls back to previous page when deleting a middle page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $page1 = NotebookPage::factory()->create(['notebook_id' => $notebook->id, 'order' => 1, 'created_by' => $user->id]);
    $page2 = NotebookPage::factory()->create(['notebook_id' => $notebook->id, 'order' => 2, 'created_by' => $user->id]);
    $page3 = NotebookPage::factory()->create(['notebook_id' => $notebook->id, 'order' => 3, 'created_by' => $user->id]);

    $action = new DeleteNotebookPageAction;
    $fallback = $action->execute($page2);

    expect($fallback?->id)->toBe($page1->id)
        ->and(NotebookPage::find($page2->id))->toBeNull()
        ->and(NotebookPage::count())->toBe(2);
});

test('it falls back to next page when deleting the first page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $page1 = NotebookPage::factory()->create(['notebook_id' => $notebook->id, 'order' => 1, 'created_by' => $user->id]);
    $page2 = NotebookPage::factory()->create(['notebook_id' => $notebook->id, 'order' => 2, 'created_by' => $user->id]);

    $action = new DeleteNotebookPageAction;
    $fallback = $action->execute($page1);

    expect($fallback?->id)->toBe($page2->id)
        ->and(NotebookPage::find($page1->id))->toBeNull()
        ->and(NotebookPage::count())->toBe(1);
});

test('it returns null fallback when deleting the only page', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $page = NotebookPage::factory()->create(['notebook_id' => $notebook->id, 'order' => 1, 'created_by' => $user->id]);

    $action = new DeleteNotebookPageAction;
    $fallback = $action->execute($page);

    expect($fallback)->toBeNull()
        ->and(NotebookPage::find($page->id))->toBeNull()
        ->and(NotebookPage::count())->toBe(0);
});
