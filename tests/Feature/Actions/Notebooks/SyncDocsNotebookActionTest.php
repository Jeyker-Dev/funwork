<?php

use App\Actions\Notebooks\SyncDocsNotebookAction;
use App\Jobs\SyncNotebookToGoogleDocJob;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('creates, updates, and prunes pages from an in-memory files array', function (): void {
    Queue::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs',
        'created_by' => $owner->id,
        'google_doc_id' => 'doc-sync-123',
        'content_changed_at' => null,
    ]);

    $action = app(SyncDocsNotebookAction::class);

    $firstCounts = $action($project, [
        'docs/01-intro.md' => '# Intro',
        'docs/02-stale.md' => '# Stale',
    ]);

    expect($firstCounts)->toBe(['created' => 2, 'updated' => 0, 'deleted' => 0]);

    $secondCounts = $action($project, [
        'docs/01-intro.md' => '# Intro Updated',
        'docs/03-guide.md' => '# Guide',
    ]);

    expect($secondCounts)->toBe(['created' => 1, 'updated' => 1, 'deleted' => 1]);

    $pages = $notebook->pages()->orderBy('order')->get();

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->source_path)->toBe('docs/01-intro.md')
        ->and($pages[0]->content)->toBe(['title' => 'docs/01-intro.md', 'body' => '# Intro Updated'])
        ->and($pages[0]->order)->toBe(1)
        ->and($pages[1]->source_path)->toBe('docs/03-guide.md')
        ->and($pages[1]->content)->toBe(['title' => 'docs/03-guide.md', 'body' => '# Guide'])
        ->and($pages[1]->order)->toBe(2)
        ->and($notebook->fresh()->content_changed_at)->not->toBeNull();

    Queue::assertPushed(SyncNotebookToGoogleDocJob::class, 1);
});

it('never prunes pages with a null source_path', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs',
        'created_by' => $owner->id,
    ]);

    $manualPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => null,
        'content' => ['title' => 'Manual Note', 'body' => 'Handcrafted'],
        'order' => 99,
        'created_by' => $owner->id,
    ]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/removed.md',
        'content' => ['title' => 'docs/removed.md', 'body' => 'Old'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    $counts = app(SyncDocsNotebookAction::class)($project, [
        'docs/added.md' => '# Added',
    ]);

    expect($counts)->toBe(['created' => 1, 'updated' => 0, 'deleted' => 1])
        ->and($manualPage->fresh())->not->toBeNull()
        ->and($manualPage->fresh()->source_path)->toBeNull()
        ->and($manualPage->fresh()->content)->toBe(['title' => 'Manual Note', 'body' => 'Handcrafted'])
        ->and($notebook->pages()->whereNotNull('source_path')->pluck('source_path')->all())->toBe(['docs/added.md']);
});

it('prunes only source_path pages when given an empty files array', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs',
        'created_by' => $owner->id,
        'content_changed_at' => null,
    ]);

    $manualPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => null,
        'content' => ['title' => 'Manual Note', 'body' => 'Keep me'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/a.md',
        'content' => ['title' => 'docs/a.md', 'body' => 'A'],
        'order' => 2,
        'created_by' => $owner->id,
    ]);

    NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/b.md',
        'content' => ['title' => 'docs/b.md', 'body' => 'B'],
        'order' => 3,
        'created_by' => $owner->id,
    ]);

    $counts = app(SyncDocsNotebookAction::class)($project, []);

    expect($counts)->toBe(['created' => 0, 'updated' => 0, 'deleted' => 2])
        ->and($notebook->pages()->count())->toBe(1)
        ->and($manualPage->fresh())->not->toBeNull()
        ->and($notebook->fresh()->content_changed_at)->not->toBeNull();
});

it('rolls back all writes when a failure occurs mid-sync', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs',
        'created_by' => $owner->id,
        'content_changed_at' => null,
    ]);

    $existingPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/existing.md',
        'content' => ['title' => 'docs/existing.md', 'body' => 'Original body'],
        'order' => 1,
        'created_by' => $owner->id,
    ]);

    $stalePage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/stale.md',
        'content' => ['title' => 'docs/stale.md', 'body' => 'Stale body'],
        'order' => 2,
        'created_by' => $owner->id,
    ]);

    $notebook->refresh();
    $notebook->forceFill(['content_changed_at' => null])->saveQuietly();

    $failOnDelete = true;

    DB::connection()->beforeExecuting(function (string $query) use (&$failOnDelete): void {
        if ($failOnDelete && str_contains(strtolower($query), 'delete from') && str_contains($query, 'notebook_pages')) {
            throw new RuntimeException('Simulated failure during prune');
        }
    });

    try {
        expect(fn (): array => app(SyncDocsNotebookAction::class)($project, [
            'docs/existing.md' => 'Updated body',
            'docs/new.md' => 'New body',
        ]))->toThrow(RuntimeException::class, 'Simulated failure during prune');
    } finally {
        $failOnDelete = false;
    }

    expect($notebook->pages()->count())->toBe(2)
        ->and($existingPage->fresh()->content)->toBe(['title' => 'docs/existing.md', 'body' => 'Original body'])
        ->and($stalePage->fresh())->not->toBeNull()
        ->and($notebook->pages()->where('source_path', 'docs/new.md')->exists())->toBeFalse()
        ->and($notebook->fresh()->content_changed_at)->toBeNull();
});

it('rolls back notebook creation when a failure occurs mid-sync', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $inserts = 0;
    $active = true;

    DB::connection()->beforeExecuting(function (string $query) use (&$inserts, &$active): void {
        if ($active && str_contains(strtolower($query), 'insert into') && str_contains($query, 'notebook_pages')) {
            $inserts++;

            if ($inserts === 2) {
                throw new RuntimeException('Simulated failure during second page insert');
            }
        }
    });

    try {
        expect(fn (): array => app(SyncDocsNotebookAction::class)($project, [
            'docs/01.md' => '# First',
            'docs/02.md' => '# Second',
        ]))->toThrow(RuntimeException::class, 'Simulated failure during second page insert');
    } finally {
        $active = false;
    }

    expect(Notebook::where('project_id', $project->id)->count())->toBe(0)
        ->and(NotebookPage::count())->toBe(0);
});

it('syncs an area notebook from an in-memory files array', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $area = Area::factory()->create(['project_id' => $project->id, 'name' => 'Backend', 'slug' => 'backend']);

    $counts = app(SyncDocsNotebookAction::class)($project, [
        'docs/00-vision.md' => '# Backend Vision',
    ], $area);

    $notebook = Notebook::where('project_id', $project->id)->where('area_id', $area->id)->sole();

    expect($counts)->toBe(['created' => 1, 'updated' => 0, 'deleted' => 0])
        ->and($notebook->name)->toBe('Docs · Backend')
        ->and($notebook->pages()->sole()->source_path)->toBe('docs/00-vision.md');
});

it('orders pages naturally and case-insensitively even when files arrive unsorted', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $counts = app(SyncDocsNotebookAction::class)($project, [
        'docs/sub/Guide.md' => '# Guide',
        'docs/README.md' => '# Readme',
        'docs/10-ten.md' => '# Ten',
        'docs/areas/backend.md' => '# Backend',
        'docs/2-two.md' => '# Two',
        'docs/01-Alpha.md' => '# Alpha',
    ]);

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->sole();
    $orderedPaths = $notebook->pages()->orderBy('order')->pluck('source_path', 'order')->all();

    expect($counts)->toBe(['created' => 6, 'updated' => 0, 'deleted' => 0])
        ->and($orderedPaths)->toBe([
            1 => 'docs/01-Alpha.md',
            2 => 'docs/2-two.md',
            3 => 'docs/10-ten.md',
            4 => 'docs/areas/backend.md',
            5 => 'docs/README.md',
            6 => 'docs/sub/Guide.md',
        ]);
});
