<?php

use App\Enums\RepositoryConnectionStatus;
use App\Models\Area;
use App\Models\Project;
use App\Models\RepositoryConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores token and webhook_secret encrypted at rest and decrypts them on access', function () {
    $plainToken = 'github_pat_secret_token_9876';
    $plainSecret = 'super-secret-webhook-value';

    $connection = RepositoryConnection::factory()->create([
        'token' => $plainToken,
        'webhook_secret' => $plainSecret,
    ]);

    $rawRow = DB::table('repository_connections')->where('id', $connection->id)->first();

    expect($rawRow)->not->toBeNull()
        ->and($rawRow->token)->not->toBe($plainToken)
        ->and($rawRow->webhook_secret)->not->toBe($plainSecret);

    $fresh = $connection->fresh();

    expect($fresh->token)->toBe($plainToken)
        ->and($fresh->webhook_secret)->toBe($plainSecret);
});

it('hides token and webhook_secret from toArray and toJson', function () {
    $connection = RepositoryConnection::factory()->create([
        'token' => 'github_pat_hidden_1234',
        'webhook_secret' => 'hidden_webhook_secret_value',
    ]);

    $array = $connection->toArray();
    $json = $connection->toJson();

    expect($array)->not->toHaveKey('token')
        ->and($array)->not->toHaveKey('webhook_secret')
        ->and($json)->not->toContain('github_pat_hidden_1234')
        ->and($json)->not->toContain('hidden_webhook_secret_value')
        ->and($json)->not->toContain('"token"')
        ->and($json)->not->toContain('"webhook_secret"');
});

it('masks the token showing only the last 4 characters', function () {
    $connection = RepositoryConnection::factory()->make([
        'token' => 'github_pat_abcdef9876',
    ]);

    expect($connection->maskedToken())->toBe('••••9876');

    $shortConnection = RepositoryConnection::factory()->make([
        'token' => 'ab',
    ]);

    expect($shortConnection->maskedToken())->toBe('••••ab');
});

it('generates a webhook secret of the configured constant length', function () {
    $first = RepositoryConnection::generateWebhookSecret();
    $second = RepositoryConnection::generateWebhookSecret();

    expect($first)->toHaveLength(RepositoryConnection::WEBHOOK_SECRET_LENGTH)
        ->and($second)->toHaveLength(RepositoryConnection::WEBHOOK_SECRET_LENGTH)
        ->and($first)->not->toBe($second);
});

it('casts status to RepositoryConnectionStatus and supports active and broken factory states', function () {
    $active = RepositoryConnection::factory()->active()->create();
    $broken = RepositoryConnection::factory()->broken('Token expired')->create();

    expect($active->status)->toBe(RepositoryConnectionStatus::Active)
        ->and($active->last_error)->toBeNull()
        ->and($broken->status)->toBe(RepositoryConnectionStatus::Broken)
        ->and($broken->last_error)->toBe('Token expired');
});

it('defines project and area relationships and Project::repositoryConnections()', function () {
    $project = Project::factory()->create();
    $area = Area::factory()->create(['project_id' => $project->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
    ]);

    expect($connection->project->is($project))->toBeTrue()
        ->and($connection->area->is($area))->toBeTrue()
        ->and($project->repositoryConnections)->toHaveCount(1)
        ->and($project->repositoryConnections->first()->is($connection))->toBeTrue();
});

it('cascades deletion from project and nullifies area_id when area is deleted', function () {
    $project = Project::factory()->create();
    $area = Area::factory()->create(['project_id' => $project->id]);
    $connection = RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
    ]);

    $area->delete();
    expect($connection->fresh()->area_id)->toBeNull();

    $project->delete();
    expect(RepositoryConnection::find($connection->id))->toBeNull();
});

it('enforces database uniqueness on project_id and area_id', function () {
    $project = Project::factory()->create();
    $area = Area::factory()->create(['project_id' => $project->id]);

    RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
    ]);

    expect(fn () => RepositoryConnection::factory()->create([
        'project_id' => $project->id,
        'area_id' => $area->id,
    ]))->toThrow(QueryException::class);
});
