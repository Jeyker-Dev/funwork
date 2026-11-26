<?php

use App\Actions\Projects\CreateDefaultAreasAction;
use App\Enums\RepositoryConnectionStatus;
use App\Jobs\SyncRepositoryDocsJob;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('S10: project settings page renders the repositories card before danger zone with 3-step instructions', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $html = $this->actingAs($owner)
        ->get(route('project.settings', $project))
        ->assertSuccessful()
        ->assertSee('Repositories')
        ->assertSee('Connect repository')
        ->assertSee('How to connect a repository')
        ->assertSee('fine-grained token')
        ->assertSee('application/json')
        ->assertSeeLivewire('projects.repository-connections')
        ->getContent();

    $repositoriesPos = strpos($html, 'id="repositories"');
    $dangerZonePos = strpos($html, 'id="danger-zone"');

    expect($repositoriesPos)->not->toBeFalse()
        ->and($dangerZonePos)->not->toBeFalse()
        ->and($repositoriesPos)->toBeLessThan($dangerZonePos);
});

test('S10: owner can create, edit, sync now, reveal/regenerate webhook secret, and remove a repository connection', function () {
    Queue::fake();

    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    app(CreateDefaultAreasAction::class)($project);
    $frontend = $project->areas()->where('slug', 'frontend')->firstOrFail();
    $backend = $project->areas()->where('slug', 'backend')->firstOrFail();

    $component = Livewire::actingAs($owner)
        ->test('projects.repository-connections', ['project' => $project])
        ->assertSet('branch', 'main')
        ->assertSet('docsPath', 'docs')
        ->call('openCreate')
        ->assertDontSee('Webhook settings')
        ->assertDontSee('/webhooks/github/repository-connections/')
        ->set('repository', 'acme/website')
        ->set('branch', 'main')
        ->set('docsPath', 'docs/architecture')
        ->set('areaId', $frontend->id)
        ->set('token', 'github_pat_initial_secret_1234')
        ->call('save')
        ->assertHasNoErrors();

    $connection = RepositoryConnection::query()->where('project_id', $project->id)->firstOrFail();

    expect($connection->repository)->toBe('acme/website')
        ->and($connection->branch)->toBe('main')
        ->and($connection->docs_path)->toBe('docs/architecture')
        ->and($connection->area_id)->toBe($frontend->id)
        ->and($connection->token)->toBe('github_pat_initial_secret_1234');

    $webhookUrl = route('webhooks.github.repository-connections', $connection);

    // Flyout stays open on the webhook step after creating; secret stays hidden until demanded.
    $component->assertSet('editingConnectionId', $connection->id)
        ->assertSet('token', '')
        ->assertSee('Webhook settings')
        ->assertSee($webhookUrl)
        ->assertSee('Regenerate secret')
        ->assertSee($connection->maskedToken())
        ->assertDontSee($connection->webhook_secret)
        ->assertDontSeeHtml('github_pat_initial_secret_1234');

    expect(json_encode($component->snapshot))
        ->not->toContain('github_pat_initial_secret_1234')
        ->not->toContain($connection->webhook_secret);

    // Reveal webhook secret on demand inside the flyout.
    $component->call('toggleWebhookSecret')
        ->assertSet('revealWebhookSecret', true)
        ->assertSee($connection->webhook_secret);

    // Regenerate secret updates the secret in DB.
    $oldSecret = $connection->webhook_secret;
    $component->call('regenerateWebhookSecret')
        ->assertHasNoErrors();

    $connection->refresh();
    expect($connection->webhook_secret)->not->toBe($oldSecret);
    $component->assertSee($connection->webhook_secret);

    // Hide secret again on toggle.
    $component->call('toggleWebhookSecret')
        ->assertSet('revealWebhookSecret', false)
        ->assertDontSee($connection->webhook_secret);

    // Edit connection without changing token preserves old token, and editing with new token rotates it.
    $component->call('openEdit', $connection->id)
        ->set('repository', 'acme/website-v2')
        ->set('branch', 'develop')
        ->set('docsPath', 'docs')
        ->set('areaId', $backend->id)
        ->set('token', '')
        ->call('save')
        ->assertHasNoErrors();

    $connection->refresh();
    expect($connection->repository)->toBe('acme/website-v2')
        ->and($connection->branch)->toBe('develop')
        ->and($connection->docs_path)->toBe('docs')
        ->and($connection->area_id)->toBe($backend->id)
        ->and($connection->token)->toBe('github_pat_initial_secret_1234');

    Queue::assertPushed(
        SyncRepositoryDocsJob::class,
        fn (SyncRepositoryDocsJob $job): bool => $job->repositoryConnection->is($connection),
    );

    // Sync now dispatches the sync job.
    Queue::fake();
    $component->call('syncNow', $connection->id)
        ->assertHasNoErrors();

    Queue::assertPushed(
        SyncRepositoryDocsJob::class,
        fn (SyncRepositoryDocsJob $job): bool => $job->repositoryConnection->is($connection),
    );

    // Removing the connection keeps the synced notebook and its pages.
    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
        'created_by' => $owner->id,
    ]);
    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'created_by' => $owner->id,
        'source_path' => 'docs/README.md',
    ]);

    $component->call('confirmRemove', $connection->id)
        ->call('removeConnection')
        ->assertHasNoErrors();

    expect(RepositoryConnection::find($connection->id))->toBeNull()
        ->and(Notebook::find($notebook->id))->not->toBeNull()
        ->and(NotebookPage::find($page->id))->not->toBeNull();
});

test('S10: non-owners receive 403 on mount and on every action', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create(['project_id' => $project->id]);

    Livewire::actingAs($intruder)
        ->test('projects.repository-connections', ['project' => $project])
        ->assertForbidden();

    $actions = [
        fn ($component) => $component->call('openCreate'),
        fn ($component) => $component->call('openEdit', $connection->id),
        fn ($component) => $component
            ->set('repository', 'acme/hack')
            ->set('token', 'github_pat_hack_1234')
            ->call('save'),
        fn ($component) => $component->call('syncNow', $connection->id),
        fn ($component) => $component->call('openEdit', $connection->id),
        fn ($component) => $component->call('confirmRemove', $connection->id),
    ];

    foreach ($actions as $invoke) {
        $project->update(['owner_id' => $owner->id]);
        $component = Livewire::actingAs($owner)
            ->test('projects.repository-connections', ['project' => $project]);

        $project->update(['owner_id' => $intruder->id]);
        $project->refresh();

        $invoke($component)->assertForbidden();
    }

    // Also verify edit-scoped webhook & remove actions forbid a non-owner after ownership changes.
    foreach (['toggleWebhookSecret', 'getWebhookSecret', 'regenerateWebhookSecret', 'removeConnection'] as $scopedAction) {
        $project->update(['owner_id' => $owner->id]);
        $editComponent = Livewire::actingAs($owner)
            ->test('projects.repository-connections', ['project' => $project])
            ->call('openEdit', $connection->id)
            ->call('confirmRemove', $connection->id);

        $project->update(['owner_id' => $intruder->id]);

        $editComponent->call($scopedAction)->assertForbidden();
    }
});

test('S10: action validation exceptions map to the corresponding form fields', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $otherProject = Project::factory()->create(['owner_id' => $owner->id]);
    $foreignArea = Area::factory()->create(['project_id' => $otherProject->id]);
    RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => null,
        'repository' => 'acme/existing',
    ]);

    $component = Livewire::actingAs($owner)
        ->test('projects.repository-connections', ['project' => $project])
        ->call('openCreate');

    // Empty token on create -> token
    $component->set('repository', 'acme/new-repo')
        ->set('branch', 'main')
        ->set('docsPath', 'docs')
        ->set('token', '   ')
        ->call('save')
        ->assertHasErrors(['token']);

    // Invalid repository -> repository
    $component->set('repository', 'https://github.com/acme/repo')
        ->set('token', 'github_pat_valid_1234')
        ->call('save')
        ->assertHasErrors(['repository']);

    // Invalid branch -> branch
    $component->set('repository', 'acme/new-repo')
        ->set('branch', '../main')
        ->call('save')
        ->assertHasErrors(['branch']);

    // Invalid docs path -> docsPath
    $component->set('branch', 'main')
        ->set('docsPath', '/docs')
        ->call('save')
        ->assertHasErrors(['docsPath']);

    // Foreign area -> areaId
    $component->set('docsPath', 'docs')
        ->set('areaId', $foreignArea->id)
        ->call('save')
        ->assertHasErrors(['areaId']);

    // Duplicate project-wide connection (null area) -> areaId
    $component->set('areaId', null)
        ->call('save')
        ->assertHasErrors(['areaId']);
});

test('S10: token never appears in rendered html or livewire snapshot before or after saving while masked token does', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $initialToken = 'github_pat_very_secret_9876';
    $rotatedToken = 'github_pat_rotated_token_4321';

    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'repository' => 'acme/docs',
        'token' => $initialToken,
    ]);

    $component = Livewire::actingAs($owner)
        ->test('projects.repository-connections', ['project' => $project]);

    // Before saving (mount + openEdit): plaintext token never leaks; masked token appears on edit.
    $component->assertDontSeeHtml($initialToken);
    expect(json_encode($component->snapshot))->not->toContain($initialToken);

    $component->call('openEdit', $connection->id)
        ->assertSet('token', '')
        ->assertSee('••••9876')
        ->assertDontSeeHtml($initialToken);
    expect(json_encode($component->snapshot))->not->toContain($initialToken);

    // After saving with a new token: rotated token is cleared from component state and absent from HTML & snapshot.
    Queue::fake();
    $component->set('token', $rotatedToken)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('token', '')
        ->assertSee('••••4321')
        ->assertDontSeeHtml($rotatedToken)
        ->assertDontSeeHtml($initialToken);

    expect(json_encode($component->snapshot))
        ->not->toContain($rotatedToken)
        ->not->toContain($initialToken);

    expect($connection->fresh()->token)->toBe($rotatedToken);
});

test('S10: broken connections display last_error and Update token while active connections show no error callout', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    app(CreateDefaultAreasAction::class)($project);
    $frontend = $project->areas()->where('slug', 'frontend')->firstOrFail();
    $backend = $project->areas()->where('slug', 'backend')->firstOrFail();

    RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $frontend->id,
        'repository' => 'acme/website',
        'branch' => 'main',
        'docs_path' => 'docs',
        'status' => RepositoryConnectionStatus::Active,
        'last_synced_sha' => '3f9c2ab1234567890',
        'last_synced_at' => now()->subMinutes(12),
        'last_error' => null,
    ]);

    $broken = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $backend->id,
        'repository' => 'acme/website-api',
        'branch' => 'main',
        'docs_path' => 'docs',
        'status' => RepositoryConnectionStatus::Broken,
        'last_synced_sha' => null,
        'last_synced_at' => null,
        'last_error' => 'GitHub rejected the token (401). It may have expired.',
    ]);

    $component = Livewire::actingAs($owner)
        ->test('projects.repository-connections', ['project' => $project])
        ->assertSee('acme/website')
        ->assertSee('Active')
        ->assertSee('Last synced')
        ->assertSee('commit 3f9c2ab')
        ->assertSee('acme/website-api')
        ->assertSee('Broken')
        ->assertSee('Never synced')
        ->assertSee('GitHub rejected the token (401). It may have expired.')
        ->assertSee('Update token');

    // Only one Update token callout (the broken connection, not the active one).
    expect(substr_count($component->html(), 'Update token'))->toBe(1);

    // Active-only project shows no callout and no Update token button.
    $broken->delete();

    Livewire::actingAs($owner)
        ->test('projects.repository-connections', ['project' => $project])
        ->assertSee('acme/website')
        ->assertSee('Active')
        ->assertDontSee('Update token')
        ->assertDontSee('Broken');
});

test('S11: empty notebooks page shows both choices, links to project settings repositories, and renders only one New notebook button', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner);

    $component = Livewire::test('pages::projects.notebooks.index', ['project' => $project]);

    $settingsRepositoriesUrl = route('project.settings', $project).'#repositories';

    $component->assertSee('No notebooks yet')
        ->assertSee('Write in FunWork')
        ->assertSee('Create a notebook and start writing pages.')
        ->assertSee('New notebook')
        ->assertSee('Connect a repository')
        ->assertSee('Connect repository')
        ->assertSee('How repository sync works')
        ->assertSeeHtml('href="'.$settingsRepositoriesUrl.'"')
        ->assertDontSeeLivewire('projects.notebooks.show-notebooks');

    // Header must not render a second "New notebook" button in the empty state.
    $dom = new DOMDocument;
    @$dom->loadHTML($component->html());
    $xpath = new DOMXPath($dom);
    $newNotebookButtons = $xpath->query('//button[normalize-space(.)="New notebook"]');
    expect($newNotebookButtons->length)->toBe(1);
});

test('S11: notebooks page hides the onboarding empty state once a notebook exists', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    Notebook::factory()->create([
        'project_id' => $project->id,
        'created_by' => $owner->id,
        'name' => 'Architecture',
    ]);

    $this->actingAs($owner);

    Livewire::test('pages::projects.notebooks.index', ['project' => $project])
        ->assertSee('Architecture')
        ->assertDontSee('Write in FunWork')
        ->assertDontSee('Connect a repository')
        ->assertDontSee('How repository sync works')
        ->assertSeeLivewire('projects.notebooks.show-notebooks');
});
