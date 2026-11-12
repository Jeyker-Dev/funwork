<?php

use App\Actions\Notebooks\CreateNotebookPageAction;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it creates the first page with order 1 when notebook is empty', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $action = new CreateNotebookPageAction;
    $page = $action($notebook, $user);

    expect($page->order)->toBe(1)
        ->and($page->created_by)->toBe($user->id)
        ->and($page->notebook_id)->toBe($notebook->id)
        ->and($page->content)->toBe(['title' => '', 'body' => ''])
        ->and(NotebookPage::count())->toBe(1);
});

test('it creates subsequent pages with incremented max order', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'order' => 5,
        'created_by' => $user->id,
    ]);

    $action = new CreateNotebookPageAction;
    $newPage = $action($notebook, $otherUser, ['title' => 'Page Title', 'body' => 'Page Body']);

    expect($newPage->order)->toBe(6)
        ->and($newPage->created_by)->toBe($otherUser->id)
        ->and($newPage->content)->toBe(['title' => 'Page Title', 'body' => 'Page Body'])
        ->and(NotebookPage::count())->toBe(2);
});
