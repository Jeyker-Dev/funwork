<?php

use App\Actions\Notebooks\SaveNotebookPageAction;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\Notebooks\NotebookPageCannotBeEmptyException;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;

uses(RefreshDatabase::class);

test('it saves page title and body', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'order' => 1,
        'created_by' => $user->id,
        'content' => ['title' => 'Initial', 'body' => 'Initial body'],
    ]);

    $action = new SaveNotebookPageAction;
    $updated = $action($page, 'New Title', 'New Body');

    expect($updated->content)->toBe(['title' => 'New Title', 'body' => 'New Body'])
        ->and($page->fresh()->content)->toBe(['title' => 'New Title', 'body' => 'New Body']);
});

test('it enforces empty page rule by throwing business rule exception and preserves content when multiple pages exist', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $existingPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'order' => 1,
        'created_by' => $user->id,
        'content' => ['title' => 'Page 1', 'body' => 'Body 1'],
    ]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'order' => 2,
        'created_by' => $user->id,
        'content' => ['title' => 'Page 2', 'body' => 'Body 2'],
    ]);

    $action = new SaveNotebookPageAction;

    expect(fn () => $action($existingPage, '   ', '  '))
        ->toThrow(
            function (NotebookPageCannotBeEmptyException $exception): void {
                expect($exception)->toBeInstanceOf(BusinessRuleException::class);
            },
            'Page cannot be empty. Delete explicitly if needed.',
        );

    expect($existingPage->fresh()->content)->toBe(['title' => 'Page 1', 'body' => 'Body 1']);
});

test('it does not report NotebookPageCannotBeEmptyException', function () {
    Exceptions::fake();

    report(new NotebookPageCannotBeEmptyException);

    Exceptions::assertNotReported(NotebookPageCannotBeEmptyException::class);
});

test('it allows empty title and body when page is the only page in notebook', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);

    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'order' => 1,
        'created_by' => $user->id,
        'content' => ['title' => 'Only Page', 'body' => 'Some content'],
    ]);

    $action = new SaveNotebookPageAction;
    $updated = $action($page, '', '');

    expect($updated->content)->toBe(['title' => '', 'body' => ''])
        ->and($page->fresh()->content)->toBe(['title' => '', 'body' => '']);
});
