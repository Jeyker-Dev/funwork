<?php

use App\Models\Project;
use App\Models\RepositoryConnection;
use App\Models\User;
use App\Notifications\RepositoryConnectionBrokenNotification;
use NotificationChannels\WebPush\WebPushChannel;

it('uses only the database channel when the owner has no push subscriptions', function (): void {
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->broken('GitHub authentication failed (HTTP 401): token expired or invalid.')->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
    ]);

    $notification = new RepositoryConnectionBrokenNotification(
        $connection,
        'GitHub authentication failed (HTTP 401): token expired or invalid.',
    );

    expect($notification->via($owner))->toBe(['database']);
});

it('includes WebPushChannel when the owner has an active push subscription', function (): void {
    $owner = User::factory()->create();
    $owner->updatePushSubscription('https://fcm.googleapis.com/fcm/send/sub1', 'key', 'auth');

    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->broken('GitHub denied access (HTTP 403).')->create([
        'project_id' => $project->id,
        'repository' => 'acme/funwork',
    ]);

    $notification = new RepositoryConnectionBrokenNotification(
        $connection,
        'GitHub denied access (HTTP 403).',
    );

    expect($notification->via($owner))->toBe(['database', WebPushChannel::class]);
});

it('formats database and web push payloads with title, repository, short reason, and project settings link without leaking the token', function (): void {
    $secretToken = 'github_pat_never_leak_token_9999';
    $webhookSecret = 'never_leak_webhook_secret_1234';
    $owner = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = RepositoryConnection::factory()->broken()->create([
        'project_id' => $project->id,
        'repository' => 'acme/docs-repo',
        'token' => $secretToken,
        'webhook_secret' => $webhookSecret,
    ]);

    $reason = 'GitHub authentication failed for [acme/docs-repo] (HTTP 401): token expired or invalid.';
    $notification = new RepositoryConnectionBrokenNotification($connection, $reason);

    $databasePayload = $notification->toDatabase($owner);
    $webPushPayload = $notification->toWebPush($owner, $notification)->toArray();
    $expectedUrl = route('project.settings', ['project' => $project->id]);

    expect($databasePayload['type'])->toBe('repository_connection_broken')
        ->and($databasePayload['title'])->toContain('acme/docs-repo')
        ->and($databasePayload['repository'])->toBe('acme/docs-repo')
        ->and($databasePayload['reason'])->toBe($reason)
        ->and($databasePayload['summary'])->toBe($reason)
        ->and($databasePayload['url'])->toBe($expectedUrl)
        ->and(json_encode($databasePayload))->not->toContain($secretToken)
        ->and(json_encode($databasePayload))->not->toContain($webhookSecret);

    expect($webPushPayload['title'])->toContain('acme/docs-repo')
        ->and($webPushPayload['body'])->toBe($reason)
        ->and($webPushPayload['icon'])->toBe('/apple-touch-icon.png')
        ->and($webPushPayload['data'])->toBe(['url' => $expectedUrl])
        ->and(json_encode($webPushPayload))->not->toContain($secretToken)
        ->and(json_encode($webPushPayload))->not->toContain($webhookSecret);
});
