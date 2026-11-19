<?php

use App\Jobs\SyncNotebookToGoogleDocJob;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/funwork_test_docs_'.uniqid('', true);
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

test('creates Docs notebook and one page per .md with title and body', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    file_put_contents($this->tempDir.'/README.md', '# Readme content');
    mkdir($this->tempDir.'/areas', 0777, true);
    file_put_contents($this->tempDir.'/areas/backend.md', '# Backend area');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 2 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();
    expect($notebook)->not->toBeNull()
        ->and($notebook->created_by)->toBe($user->id);

    $pages = $notebook->pages()->orderBy('order')->get();
    expect($pages)->toHaveCount(2);

    $readmePage = $pages->firstWhere('source_path', 'docs/README.md');
    $backendPage = $pages->firstWhere('source_path', 'docs/areas/backend.md');

    expect($readmePage)->not->toBeNull()
        ->and($readmePage->content['title'])->toBe('docs/README.md')
        ->and($readmePage->content['body'])->toBe('# Readme content')
        ->and($readmePage->created_by)->toBe($user->id);

    expect($backendPage)->not->toBeNull()
        ->and($backendPage->content['title'])->toBe('docs/areas/backend.md')
        ->and($backendPage->content['body'])->toBe('# Backend area')
        ->and($backendPage->created_by)->toBe($user->id)
        ->and($backendPage->order)->toBe(1)
        ->and($readmePage->order)->toBe(2);
});

test('ignores archive directory', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    file_put_contents($this->tempDir.'/valid.md', '# Valid content');
    mkdir($this->tempDir.'/archive', 0777, true);
    file_put_contents($this->tempDir.'/archive/archived.md', '# Archived content');
    mkdir($this->tempDir.'/archive/nested', 0777, true);
    file_put_contents($this->tempDir.'/archive/nested/deep.md', '# Deep archived content');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 1 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();
    $pages = $notebook->pages()->get();

    expect($pages)->toHaveCount(1)
        ->and($pages->first()->source_path)->toBe('docs/valid.md');
});

test('updates page when file content changes without duplicating', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    file_put_contents($this->tempDir.'/spec.md', '# Initial Spec');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 1 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();
    expect($notebook->pages()->count())->toBe(1);

    file_put_contents($this->tempDir.'/spec.md', '# Updated Spec');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 0 creadas, 1 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $pages = $notebook->pages()->get();
    expect($pages)->toHaveCount(1)
        ->and($pages->first()->content['body'])->toBe('# Updated Spec');
});

test('deletes page when its file is removed from disk', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    file_put_contents($this->tempDir.'/keep.md', '# Keep');
    file_put_contents($this->tempDir.'/remove.md', '# Remove');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 2 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();
    expect($notebook->pages()->count())->toBe(2);

    unlink($this->tempDir.'/remove.md');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 0 creadas, 0 actualizadas, 1 borradas.')
        ->assertSuccessful();

    $pages = $notebook->pages()->get();
    expect($pages)->toHaveCount(1)
        ->and($pages->first()->source_path)->toBe('docs/keep.md');
});

test('manual page with null source_path survives synchronization', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    file_put_contents($this->tempDir.'/synced.md', '# Synced');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();

    $manualPage = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => null,
        'content' => ['title' => 'Manual Note', 'body' => 'Handcrafted notes'],
        'order' => 99,
        'created_by' => $user->id,
    ]);

    unlink($this->tempDir.'/synced.md');
    file_put_contents($this->tempDir.'/new_synced.md', '# New Synced');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 1 creadas, 0 actualizadas, 1 borradas.')
        ->assertSuccessful();

    $freshManual = $manualPage->fresh();
    expect($freshManual)->not->toBeNull()
        ->and($freshManual->source_path)->toBeNull()
        ->and($freshManual->content['body'])->toBe('Handcrafted notes');
});

test('non-existent project returns exit code 1 and creates nothing', function (): void {
    file_put_contents($this->tempDir.'/doc.md', '# Doc');

    $this->artisan('funwork:sync-docs', [
        'project' => 999999,
        '--path' => $this->tempDir,
    ])
        ->assertExitCode(1);

    expect(Notebook::where('name', 'Docs')->count())->toBe(0)
        ->and(NotebookPage::count())->toBe(0);
});

test('idempotency: running consecutively without disk changes modifies nothing', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    file_put_contents($this->tempDir.'/doc1.md', '# Doc 1');
    file_put_contents($this->tempDir.'/doc2.md', '# Doc 2');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 2 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();
    $firstRunUpdatedAt = $notebook->pages()->pluck('updated_at', 'id');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])
        ->expectsOutput('Docs sincronizados: 0 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $secondRunUpdatedAt = $notebook->pages()->pluck('updated_at', 'id');
    expect($secondRunUpdatedAt->all())->toEqual($firstRunUpdatedAt->all());
});

test('does not touch notebooks of other projects', function (): void {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $user->id]);
    $projectB = Project::factory()->create(['owner_id' => $user->id]);

    $notebookA = Notebook::factory()->create([
        'project_id' => $projectA->id,
        'name' => 'Docs',
        'created_by' => $user->id,
    ]);

    $pageA = NotebookPage::factory()->create([
        'notebook_id' => $notebookA->id,
        'source_path' => 'docs/project_a_file.md',
        'content' => ['title' => 'docs/project_a_file.md', 'body' => 'Project A content'],
        'order' => 1,
        'created_by' => $user->id,
    ]);

    file_put_contents($this->tempDir.'/project_b_file.md', '# Project B content');

    $this->artisan('funwork:sync-docs', [
        'project' => $projectB->id,
        '--path' => $this->tempDir,
    ])->assertSuccessful();

    expect($pageA->fresh())->not->toBeNull()
        ->and($pageA->fresh()->content['body'])->toBe('Project A content');

    $notebookB = Notebook::where('project_id', $projectB->id)->where('name', 'Docs')->first();
    expect($notebookB)->not->toBeNull()
        ->and($notebookB->pages()->count())->toBe(1)
        ->and($notebookB->pages()->first()->source_path)->toBe('docs/project_b_file.md');
});

test('syncing different areas with colliding filenames creates separate notebooks without overwriting or deleting each other', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $frontend = Area::factory()->create(['project_id' => $project->id, 'name' => 'Frontend', 'slug' => 'frontend']);
    $backend = Area::factory()->create(['project_id' => $project->id, 'name' => 'Backend', 'slug' => 'backend']);

    [$frontendDir, $backendDir] = [$this->tempDir.'/frontend', $this->tempDir.'/backend'];
    mkdir($frontendDir, 0777, true);
    mkdir($backendDir, 0777, true);
    file_put_contents($frontendDir.'/00-vision.md', '# Frontend Vision');
    file_put_contents($backendDir.'/00-vision.md', '# Backend Vision');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $frontendDir, '--area' => 'frontend'])
        ->expectsOutput('Docs sincronizados: 1 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();
    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $backendDir, '--area' => 'backend'])
        ->expectsOutput('Docs sincronizados: 1 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $frontendNotebook = Notebook::where('project_id', $project->id)->where('area_id', $frontend->id)->first();
    $backendNotebook = Notebook::where('project_id', $project->id)->where('area_id', $backend->id)->first();

    expect($frontendNotebook->name)->toBe('Docs · Frontend')
        ->and($frontendNotebook->created_by)->toBe($user->id)
        ->and($frontendNotebook->pages()->sole()->content['body'])->toBe('# Frontend Vision')
        ->and($backendNotebook->name)->toBe('Docs · Backend')
        ->and($backendNotebook->created_by)->toBe($user->id)
        ->and($backendNotebook->pages()->sole()->content['body'])->toBe('# Backend Vision');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $frontendDir, '--area' => 'frontend'])
        ->expectsOutput('Docs sincronizados: 0 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    expect($backendNotebook->pages()->sole()->content['body'])->toBe('# Backend Vision');
});

test('unknown --area slug fails with non-zero exit code and creates no notebook', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $otherProject = Project::factory()->create(['owner_id' => $user->id]);
    Area::factory()->create(['project_id' => $otherProject->id, 'name' => 'Mobile', 'slug' => 'mobile']);
    file_put_contents($this->tempDir.'/00-vision.md', '# Vision');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir, '--area' => 'mobile'])
        ->expectsOutput("Area [mobile] not found in project [{$project->id}].")
        ->assertFailed();

    expect(Notebook::where('project_id', $project->id)->count())->toBe(0)
        ->and(NotebookPage::count())->toBe(0);
});

test('without --area, pages go to notebook Docs with area_id null', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    Area::factory()->create(['project_id' => $project->id, 'name' => 'Frontend', 'slug' => 'frontend']);
    file_put_contents($this->tempDir.'/00-vision.md', '# Root Vision');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir])
        ->expectsOutput('Docs sincronizados: 1 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();
    expect($notebook)->not->toBeNull()
        ->and($notebook->area_id)->toBeNull()
        ->and($notebook->pages()->sole()->content['body'])->toBe('# Root Vision');
});

test('removing a file from one area directory deletes only that page on re-sync', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $frontend = Area::factory()->create(['project_id' => $project->id, 'name' => 'Frontend', 'slug' => 'frontend']);
    $backend = Area::factory()->create(['project_id' => $project->id, 'name' => 'Backend', 'slug' => 'backend']);

    [$frontendDir, $backendDir] = [$this->tempDir.'/frontend', $this->tempDir.'/backend'];
    mkdir($frontendDir, 0777, true);
    mkdir($backendDir, 0777, true);
    file_put_contents($frontendDir.'/00-vision.md', '# Frontend Vision');
    file_put_contents($frontendDir.'/01-extra.md', '# Frontend Extra');
    file_put_contents($backendDir.'/00-vision.md', '# Backend Vision');
    file_put_contents($backendDir.'/01-extra.md', '# Backend Extra');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $frontendDir, '--area' => 'frontend'])->assertSuccessful();
    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $backendDir, '--area' => 'backend'])->assertSuccessful();

    unlink($frontendDir.'/01-extra.md');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $frontendDir, '--area' => 'frontend'])
        ->expectsOutput('Docs sincronizados: 0 creadas, 0 actualizadas, 1 borradas.')
        ->assertSuccessful();

    $frontendNotebook = Notebook::where('project_id', $project->id)->where('area_id', $frontend->id)->first();
    $backendNotebook = Notebook::where('project_id', $project->id)->where('area_id', $backend->id)->first();

    expect($frontendNotebook->pages()->pluck('source_path')->all())->toBe(['docs/00-vision.md'])
        ->and($backendNotebook->pages()->orderBy('order')->pluck('source_path')->all())->toBe(['docs/00-vision.md', 'docs/01-extra.md']);
});

test('area notebook updates content_changed_at and dispatches delayed Google Doc sync job on changes', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $area = Area::factory()->create(['project_id' => $project->id, 'name' => 'Backend', 'slug' => 'backend']);
    $notebook = Notebook::factory()->for($project)->create([
        'name' => 'Docs · Backend',
        'area_id' => $area->id,
        'created_by' => $user->id,
        'google_doc_id' => 'doc-backend-123',
    ]);
    file_put_contents($this->tempDir.'/00-vision.md', '# Backend Vision');
    Queue::fake();

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir, '--area' => 'backend'])
        ->assertSuccessful();

    expect($notebook->fresh()->content_changed_at)->not->toBeNull();
    Queue::assertPushed(SyncNotebookToGoogleDocJob::class, fn (SyncNotebookToGoogleDocJob $job): bool => $job->uniqueId() === (string) $notebook->id && $job->delay !== null);
    Queue::assertPushed(SyncNotebookToGoogleDocJob::class, 1);
});

test('updates existing page order when a new file sorts before it', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    file_put_contents($this->tempDir.'/02-spec.md', '# Spec');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir])->assertSuccessful();
    file_put_contents($this->tempDir.'/01-intro.md', '# Intro');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir])
        ->expectsOutput('Docs sincronizados: 1 creadas, 1 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();
    expect($notebook->pages()->where('source_path', 'docs/02-spec.md')->value('order'))->toBe(2);
});

test('updates existing page created_by when project owner changes', function (): void {
    [$originalOwner, $newOwner] = User::factory()->count(2)->create();
    $project = Project::factory()->create(['owner_id' => $originalOwner->id]);
    file_put_contents($this->tempDir.'/01-intro.md', '# Intro');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir])->assertSuccessful();
    $project->update(['owner_id' => $newOwner->id]);

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir])
        ->expectsOutput('Docs sincronizados: 0 creadas, 1 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();
    expect($notebook->pages()->sole()->created_by)->toBe($newOwner->id);
});

test('non-existent --path directory fails with non-zero exit code and creates nothing', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $missingPath = $this->tempDir.'/does-not-exist';

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $missingPath])
        ->expectsOutput("Docs directory [{$missingPath}] not found.")
        ->assertFailed();

    expect(Notebook::where('project_id', $project->id)->count())->toBe(0)
        ->and(NotebookPage::count())->toBe(0);
});

test('unreadable markdown file fails fast with RuntimeException', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $unreadableFile = $this->tempDir.'/secret.md';
    file_put_contents($unreadableFile, '# Secret');
    chmod($unreadableFile, 0000);

    try {
        expect(function () use ($project): void {
            $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir])->execute();
        })->toThrow(RuntimeException::class);
    } finally {
        chmod($unreadableFile, 0644);
    }

    expect(Notebook::where('project_id', $project->id)->count())->toBe(0)
        ->and(NotebookPage::count())->toBe(0);
});

test('renaming an area reuses the same notebook and updates its name without creating an orphan', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $area = Area::factory()->create(['project_id' => $project->id, 'name' => 'Frontend', 'slug' => 'frontend']);
    file_put_contents($this->tempDir.'/00-vision.md', '# Initial Vision');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir, '--area' => 'frontend'])
        ->assertSuccessful();

    $initialNotebook = Notebook::where('project_id', $project->id)->where('area_id', $area->id)->sole();
    expect($initialNotebook->name)->toBe('Docs · Frontend');

    $area->update(['name' => 'Client App']);
    file_put_contents($this->tempDir.'/00-vision.md', '# Updated Vision');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir, '--area' => 'frontend'])
        ->expectsOutput('Docs sincronizados: 0 creadas, 1 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $reusedNotebook = Notebook::where('project_id', $project->id)->where('area_id', $area->id)->sole();
    expect($reusedNotebook->id)->toBe($initialNotebook->id)
        ->and($reusedNotebook->name)->toBe('Docs · Client App')
        ->and($reusedNotebook->pages()->sole()->content['body'])->toBe('# Updated Vision')
        ->and(Notebook::where('project_id', $project->id)->where('name', 'Docs · Frontend')->exists())->toBeFalse();
});

test('syncing an area leaves manual notebooks in that area untouched and creates a separate Docs notebook', function (): void {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $backend = Area::factory()->create(['project_id' => $project->id, 'name' => 'Backend', 'slug' => 'backend']);

    $manualNotebook = Notebook::factory()->for($project)->create([
        'area_id' => $backend->id,
        'name' => 'Backend notes',
        'created_by' => $user->id,
    ]);
    $manualPage = NotebookPage::factory()->create([
        'notebook_id' => $manualNotebook->id,
        'source_path' => null,
        'content' => ['title' => 'Manual Note', 'body' => 'Keep me intact'],
        'order' => 1,
        'created_by' => $user->id,
    ]);

    file_put_contents($this->tempDir.'/00-vision.md', '# Backend Vision');

    $this->artisan('funwork:sync-docs', ['project' => $project->id, '--path' => $this->tempDir, '--area' => 'backend'])
        ->expectsOutput('Docs sincronizados: 1 creadas, 0 actualizadas, 0 borradas.')
        ->assertSuccessful();

    $manualNotebook->refresh();
    $docsNotebook = Notebook::where('project_id', $project->id)->where('area_id', $backend->id)->where('name', 'Docs · Backend')->sole();

    expect($manualNotebook->name)->toBe('Backend notes')
        ->and($manualNotebook->pages()->whereNotNull('source_path')->count())->toBe(0)
        ->and($manualNotebook->pages()->sole()->id)->toBe($manualPage->id)
        ->and($manualPage->fresh()->content['body'])->toBe('Keep me intact')
        ->and($docsNotebook->id)->not->toBe($manualNotebook->id)
        ->and($docsNotebook->pages()->sole()->content['body'])->toBe('# Backend Vision');
});
