<?php

use App\Enums\AiProvider;
use App\Models\AiConnection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('stores api_key encrypted at rest and decrypts it on access', function () {
    $plainKey = 'sk-proj-secret-test-key-9876';

    $connection = AiConnection::factory()->create([
        'api_key' => $plainKey,
    ]);

    $rawRow = DB::table('ai_connections')->where('id', $connection->id)->first();

    expect($rawRow)->not->toBeNull()
        ->and($rawRow->api_key)->not->toBe($plainKey);

    expect($connection->fresh()->api_key)->toBe($plainKey);
});

it('hides api_key from toArray and toJson', function () {
    $plainKey = 'sk-or-v1-hidden-secret-1234';

    $connection = AiConnection::factory()->create([
        'api_key' => $plainKey,
    ]);

    $array = $connection->toArray();
    $json = $connection->toJson();

    expect($array)->not->toHaveKey('api_key')
        ->and($json)->not->toContain($plainKey)
        ->and($json)->not->toContain('"api_key"');
});

it('masks api_key showing only the last 4 characters', function () {
    $connection = AiConnection::factory()->make([
        'api_key' => 'sk-or-v1-abcdef7f3a',
    ]);

    expect($connection->maskedApiKey())->toBe('••••7f3a');

    $shortConnection = AiConnection::factory()->make([
        'api_key' => 'ab',
    ]);

    expect($shortConnection->maskedApiKey())->toBe('••••ab');
});

it('casts provider, allow_paid_models, and is_default and defines User::aiConnections()', function () {
    $user = User::factory()->create();
    $connection = AiConnection::factory()->openRouter()->default()->create([
        'user_id' => $user->id,
        'allow_paid_models' => false,
    ]);

    $fresh = $connection->fresh();

    expect($fresh->provider)->toBe(AiProvider::OpenRouter)
        ->and($fresh->allow_paid_models)->toBeFalse()
        ->and($fresh->is_default)->toBeTrue()
        ->and($fresh->user->is($user))->toBeTrue()
        ->and($user->aiConnections)->toHaveCount(1)
        ->and($user->aiConnections->first()->is($connection))->toBeTrue();
});

it('cascades deletion when the owning user is deleted', function () {
    $user = User::factory()->create();
    $connection = AiConnection::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect(AiConnection::find($connection->id))->toBeNull();
});
