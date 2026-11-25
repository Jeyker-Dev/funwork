<?php

use App\Actions\RepositoryConnections\CreateRepositoryConnectionAction;
use App\Actions\RepositoryConnections\DeleteRepositoryConnectionAction;
use App\Actions\RepositoryConnections\RegenerateWebhookSecretAction;
use App\Actions\RepositoryConnections\UpdateRepositoryConnectionAction;
use App\Data\RepositoryConnections\RepositoryConnectionData;
use App\Enums\RepositoryConnectionStatus;
use App\Exceptions\RepositoryConnections\AreaDoesNotBelongToProjectException;
use App\Exceptions\RepositoryConnections\DuplicateRepositoryConnectionException;
use App\Exceptions\RepositoryConnections\EmptyRepositoryTokenException;
use App\Exceptions\RepositoryConnections\InvalidBranchException;
use App\Exceptions\RepositoryConnections\InvalidDocsPathException;
use App\Exceptions\RepositoryConnections\InvalidRepositoryException;
use App\Models\Area;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

it('creates a repository connection with encrypted token and generated webhook secret', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $area = Area::factory()->create(['project_id' => $project->id]);
    $action = app(CreateRepositoryConnectionAction::class);

    $connection = $action($project, $owner, new RepositoryConnectionData(
        repository: 'acme/funwork',
        branch: 'main',
        docsPath: 'docs/architecture',
        token: 'ghp_initial_1234',
        area: $area,
    ));

    expect($connection->project_id)->toBe($project->id)
        ->and($connection->area_id)->toBe($area->id)
        ->and($connection->repository)->toBe('acme/funwork')
        ->and($connection->branch)->toBe('main')
        ->and($connection->docs_path)->toBe('docs/architecture')
        ->and($connection->token)->toBe('ghp_initial_1234')
        ->and($connection->webhook_secret)->toHaveLength(RepositoryConnection::WEBHOOK_SECRET_LENGTH)
        ->and($connection->status)->toBe(RepositoryConnectionStatus::Active);
});

it('rejects duplicate connection for the same project and area', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $area = Area::factory()->create(['project_id' => $project->id]);
    $action = app(CreateRepositoryConnectionAction::class);

    $action($project, $owner, new RepositoryConnectionData(
        repository: 'acme/api',
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_first_1234',
        area: $area,
    ));

    expect(fn () => $action($project, $owner, new RepositoryConnectionData(
        repository: 'acme/other',
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_second_1234',
        area: $area,
    )))->toThrow(DuplicateRepositoryConnectionException::class);

    expect(RepositoryConnection::count())->toBe(1);
});

it('allows at most one connection without area per project', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $otherProject = Project::factory()->create(['owner_id' => $owner->id]);
    $action = app(CreateRepositoryConnectionAction::class);

    $action($project, $owner, new RepositoryConnectionData(
        repository: 'acme/app',
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_first_1234',
        area: null,
    ));

    expect(fn () => $action($project, $owner, new RepositoryConnectionData(
        repository: 'acme/second-app',
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_second_1234',
        area: null,
    )))->toThrow(DuplicateRepositoryConnectionException::class);

    $otherConnection = $action($otherProject, $owner, new RepositoryConnectionData(
        repository: 'acme/other-project',
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_other_1234',
        area: null,
    ));

    expect($otherConnection->exists)->toBeTrue()
        ->and(RepositoryConnection::count())->toBe(2);
});

it('rejects an area belonging to another project on create and update', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $otherProject = Project::factory()->create(['owner_id' => $owner->id]);
    $foreignArea = Area::factory()->create(['project_id' => $otherProject->id]);

    $createAction = app(CreateRepositoryConnectionAction::class);
    $updateAction = app(UpdateRepositoryConnectionAction::class);

    expect(fn () => $createAction($project, $owner, new RepositoryConnectionData(
        repository: 'acme/app',
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_token_1234',
        area: $foreignArea,
    )))->toThrow(AreaDoesNotBelongToProjectException::class);

    $connection = RepositoryConnection::factory()->create(['project_id' => $project->id]);

    expect(fn () => $updateAction($connection, $owner, new RepositoryConnectionData(
        repository: 'acme/app',
        branch: 'main',
        docsPath: 'docs',
        token: null,
        area: $foreignArea,
    )))->toThrow(AreaDoesNotBelongToProjectException::class);
});

it('rejects invalid repository formats', function (string $invalidRepository) {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $createAction = app(CreateRepositoryConnectionAction::class);

    expect(fn () => $createAction($project, $owner, new RepositoryConnectionData(
        repository: $invalidRepository,
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_token_1234',
    )))->toThrow(InvalidRepositoryException::class);
})->with([
    'empty' => '',
    'owner only' => 'acme',
    'missing name' => 'acme/',
    'missing owner' => '/funwork',
    'extra segment' => 'acme/funwork/extra',
    'full url' => 'https://github.com/acme/funwork',
    'parent traversal' => '../funwork',
    'dot dot repo' => 'acme/..',
    'dot repo' => 'acme/.',
    'spaces' => 'acme / funwork',
]);

it('rejects invalid branch names', function (string $invalidBranch) {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $createAction = app(CreateRepositoryConnectionAction::class);

    expect(fn () => $createAction($project, $owner, new RepositoryConnectionData(
        repository: 'acme/funwork',
        branch: $invalidBranch,
        docsPath: 'docs',
        token: 'ghp_token_1234',
    )))->toThrow(InvalidBranchException::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'leading slash' => '/main',
    'parent traversal' => '../main',
    'nested traversal' => 'feature/../main',
    'trailing slash' => 'main/',
    'backslash' => 'feature\\main',
    'spaces' => 'my branch',
    'dot segment' => './main',
]);

it('rejects invalid docs_path values', function (string $invalidDocsPath) {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $createAction = app(CreateRepositoryConnectionAction::class);

    expect(fn () => $createAction($project, $owner, new RepositoryConnectionData(
        repository: 'acme/funwork',
        branch: 'main',
        docsPath: $invalidDocsPath,
        token: 'ghp_token_1234',
    )))->toThrow(InvalidDocsPathException::class);
})->with([
    'empty' => '',
    'whitespace only' => '   ',
    'leading slash' => '/docs',
    'parent traversal' => '../docs',
    'nested traversal' => 'docs/../secrets',
    'trailing slash' => 'docs/',
    'backslash' => 'docs\\guide',
    'spaces' => 'my docs',
    'dot segment' => './docs',
]);

it('rejects creating a connection with an empty token', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $createAction = app(CreateRepositoryConnectionAction::class);

    expect(fn () => $createAction($project, $owner, new RepositoryConnectionData(
        repository: 'acme/funwork',
        branch: 'main',
        docsPath: 'docs',
        token: '   ',
    )))->toThrow(EmptyRepositoryTokenException::class);
});

it('keeps the previous token when updating with an empty token and replaces it when provided', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $area = Area::factory()->create(['project_id' => $project->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'repository' => 'acme/old',
        'branch' => 'main',
        'docs_path' => 'docs',
        'token' => 'ghp_original_1234',
    ]);

    $updateAction = app(UpdateRepositoryConnectionAction::class);

    $updatedWithoutToken = $updateAction($connection, $owner, new RepositoryConnectionData(
        repository: 'acme/new',
        branch: 'develop',
        docsPath: 'docs/v2',
        token: '',
        area: $area,
    ));

    expect($updatedWithoutToken->repository)->toBe('acme/new')
        ->and($updatedWithoutToken->branch)->toBe('develop')
        ->and($updatedWithoutToken->docs_path)->toBe('docs/v2')
        ->and($updatedWithoutToken->area_id)->toBe($area->id)
        ->and($updatedWithoutToken->fresh()->token)->toBe('ghp_original_1234');

    $updatedWithToken = $updateAction($updatedWithoutToken, $owner, new RepositoryConnectionData(
        repository: 'acme/new',
        branch: 'develop',
        docsPath: 'docs/v2',
        token: 'ghp_rotated_5678',
        area: $area,
    ));

    expect($updatedWithToken->fresh()->token)->toBe('ghp_rotated_5678');

    $updatedWithoutArea = $updateAction($updatedWithToken, $owner, new RepositoryConnectionData(
        repository: 'acme/new',
        branch: 'feature/docs-sync',
        token: '   ',
        area: null,
    ));

    expect($updatedWithoutArea->area_id)->toBeNull()
        ->and($updatedWithoutArea->area)->toBeNull()
        ->and($updatedWithoutArea->branch)->toBe('feature/docs-sync')
        ->and($updatedWithoutArea->docs_path)->toBe('docs')
        ->and($updatedWithoutArea->fresh()->token)->toBe('ghp_rotated_5678');
});

it('rejects updating a connection into a duplicate area or second null-area slot', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $area = Area::factory()->create(['project_id' => $project->id]);

    $withoutArea = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => null,
    ]);
    $withArea = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
    ]);

    $updateAction = app(UpdateRepositoryConnectionAction::class);

    expect(fn () => $updateAction($withArea, $owner, new RepositoryConnectionData(
        repository: $withArea->repository,
        branch: $withArea->branch,
        docsPath: $withArea->docs_path,
        area: null,
    )))->toThrow(DuplicateRepositoryConnectionException::class);

    expect(fn () => $updateAction($withoutArea, $owner, new RepositoryConnectionData(
        repository: $withoutArea->repository,
        branch: $withoutArea->branch,
        docsPath: $withoutArea->docs_path,
        area: $area,
    )))->toThrow(DuplicateRepositoryConnectionException::class);
});

it('regenerates the webhook secret with a new secret', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'webhook_secret' => 'old-webhook-secret',
    ]);

    $action = app(RegenerateWebhookSecretAction::class);
    $updated = $action($connection, $owner);

    expect($updated->webhook_secret)->not->toBe('old-webhook-secret')
        ->and($updated->webhook_secret)->toHaveLength(RepositoryConnection::WEBHOOK_SECRET_LENGTH)
        ->and($connection->fresh()->webhook_secret)->toBe($updated->webhook_secret);
});

it('deletes a connection while keeping the notebook and its pages', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $area = Area::factory()->create(['project_id' => $project->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
    ]);

    $notebook = Notebook::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
        'name' => 'Docs · '.$area->name,
        'created_by' => $owner->id,
    ]);
    $page = NotebookPage::factory()->create([
        'notebook_id' => $notebook->id,
        'source_path' => 'docs/README.md',
        'created_by' => $owner->id,
    ]);

    $action = app(DeleteRepositoryConnectionAction::class);
    $action($connection, $owner);

    expect(RepositoryConnection::find($connection->id))->toBeNull()
        ->and(Notebook::find($notebook->id))->not->toBeNull()
        ->and(NotebookPage::find($page->id))->not->toBeNull();
});

it('forbids non-owners from invoking repository connection actions', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create(['project_id' => $project->id]);

    $dto = new RepositoryConnectionData(
        repository: 'acme/funwork',
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_token_1234',
    );

    expect(fn () => app(CreateRepositoryConnectionAction::class)($project, $otherUser, $dto))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateRepositoryConnectionAction::class)($connection, $otherUser, $dto))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(RegenerateWebhookSecretAction::class)($connection, $otherUser))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteRepositoryConnectionAction::class)($connection, $otherUser))
        ->toThrow(AuthorizationException::class);
});

it('resets the last synced commit when the sync source changes', function (string $changedField) {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $area = Area::factory()->create(['project_id' => $project->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'repository' => 'acme/app',
        'branch' => 'main',
        'docs_path' => 'docs',
        'last_synced_sha' => 'abc123',
        'last_synced_at' => now(),
    ]);

    $input = [
        'repository' => 'acme/app',
        'branch' => 'main',
        'docsPath' => 'docs',
        'token' => '',
        'area' => null,
    ];

    $input = match ($changedField) {
        'repository' => [...$input, 'repository' => 'acme/api'],
        'branch' => [...$input, 'branch' => 'develop'],
        'docs_path' => [...$input, 'docsPath' => 'documentation'],
        'area' => [...$input, 'area' => $area],
    };

    $updated = app(UpdateRepositoryConnectionAction::class)($connection, $owner, new RepositoryConnectionData(...$input));

    expect($updated->fresh()->last_synced_sha)->toBeNull()
        ->and($updated->fresh()->last_synced_at)->toBeNull();
})->with(['repository', 'branch', 'docs_path', 'area']);

it('keeps the last synced commit when only the token changes', function () {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'repository' => 'acme/app',
        'branch' => 'main',
        'docs_path' => 'docs',
        'last_synced_sha' => 'abc123',
        'last_synced_at' => now(),
    ]);

    $updated = app(UpdateRepositoryConnectionAction::class)($connection, $owner, new RepositoryConnectionData(
        repository: 'acme/app',
        branch: 'main',
        docsPath: 'docs',
        token: 'ghp_rotated_9999',
        area: null,
    ));

    expect($updated->fresh()->last_synced_sha)->toBe('abc123')
        ->and($updated->fresh()->last_synced_at)->not->toBeNull();
});
