<?php

use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/funwork_test_docs_'.uniqid('', true);
    mkdir($this->tempDir, 0777, true);
});

afterEach(function () {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

test('creates Docs notebook and one page per .md with title and body', function () {
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

test('ignores archive directory', function () {
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

test('updates page when file content changes without duplicating', function () {
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

test('deletes page when its file is removed from disk', function () {
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

test('manual page with null source_path survives synchronization', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    file_put_contents($this->tempDir.'/synced.md', '# Synced');

    $this->artisan('funwork:sync-docs', [
        'project' => $project->id,
        '--path' => $this->tempDir,
    ])->assertSuccessful();

    $notebook = Notebook::where('project_id', $project->id)->where('name', 'Docs')->first();

    $manualPage = NotebookPage::create([
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

test('non-existent project returns exit code 1 and creates nothing', function () {
    file_put_contents($this->tempDir.'/doc.md', '# Doc');

    $this->artisan('funwork:sync-docs', [
        'project' => 999999,
        '--path' => $this->tempDir,
    ])
        ->assertExitCode(1);

    expect(Notebook::where('name', 'Docs')->count())->toBe(0)
        ->and(NotebookPage::count())->toBe(0);
});

test('idempotency: running consecutively without disk changes modifies nothing', function () {
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

test('does not touch notebooks of other projects', function () {
    $user = User::factory()->create();
    $projectA = Project::factory()->create(['owner_id' => $user->id]);
    $projectB = Project::factory()->create(['owner_id' => $user->id]);

    $notebookA = Notebook::create([
        'project_id' => $projectA->id,
        'name' => 'Docs',
        'created_by' => $user->id,
    ]);

    $pageA = NotebookPage::create([
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
