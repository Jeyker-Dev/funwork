<?php

use App\Models\GoogleAccount;
use App\Models\User;
use App\Services\Google\GoogleAuthorizationException;
use App\Services\Google\GoogleDriveClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

function googleAccount(array $attributes = []): GoogleAccount
{
    return GoogleAccount::create([
        'user_id' => User::factory()->create()->id,
        'google_id' => 'google-123',
        'email' => 'owner@gmail.com',
        'access_token' => 'valid-access',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
        ...$attributes,
    ]);
}

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
    ]);
});

test('createDocument uploads markdown converted to a Google Doc and returns its ID', function () {
    Http::fake(['www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'doc-1'])]);

    $id = app(GoogleDriveClient::class)->createDocument(googleAccount(), 'Fun Work — Project pack', "# Hello\n");

    expect($id)->toBe('doc-1');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'POST'
            && str_contains($request->url(), 'uploadType=multipart')
            && $request->hasHeader('Authorization', 'Bearer valid-access')
            && str_starts_with($request->header('Content-Type')[0], 'multipart/related; boundary=')
            && str_contains($request->body(), '"mimeType":"application\/vnd.google-apps.document"')
            && str_contains($request->body(), "Content-Type: text/markdown; charset=UTF-8\r\n\r\n# Hello\n");
    });
});

test('replaceDocument replaces the content of the same file', function () {
    Http::fake(['www.googleapis.com/upload/drive/v3/files/doc-1*' => Http::response(['id' => 'doc-1', 'trashed' => false])]);

    expect(app(GoogleDriveClient::class)->replaceDocument(googleAccount(), 'doc-1', "# New\n"))->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
        && str_contains($request->url(), '/files/doc-1?uploadType=media')
        && $request->body() === "# New\n");
});

test('replaceDocument reports a missing or trashed file', function (array $response, int $status) {
    Http::fake(['www.googleapis.com/upload/drive/v3/files/*' => Http::response($response, $status)]);

    expect(app(GoogleDriveClient::class)->replaceDocument(googleAccount(), 'doc-1', 'x'))->toBeFalse();
})->with([
    'deleted' => [['error' => ['code' => 404]], 404],
    'trashed' => [['id' => 'doc-1', 'trashed' => true], 200],
]);

test('an expired access token is refreshed and stored before calling Drive', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-access', 'expires_in' => 3599]),
        'www.googleapis.com/upload/drive/v3/files*' => Http::response(['id' => 'doc-1']),
    ]);

    $account = googleAccount(['access_token' => 'stale-access', 'expires_at' => now()->subMinute()]);

    app(GoogleDriveClient::class)->createDocument($account, 'Pack', 'x');

    expect($account->fresh())
        ->access_token->toBe('fresh-access')
        ->and($account->fresh()->expires_at->isFuture())->toBeTrue();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'refresh-token');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'upload/drive')
        && $request->hasHeader('Authorization', 'Bearer fresh-access'));
});

test('a 401 from Drive refreshes the token and retries once', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'fresh-access', 'expires_in' => 3599]),
        'www.googleapis.com/upload/drive/v3/files*' => Http::sequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['id' => 'doc-1']),
    ]);

    expect(app(GoogleDriveClient::class)->createDocument(googleAccount(), 'Pack', 'x'))->toBe('doc-1');
});

test('a revoked refresh token asks the user to connect again', function () {
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    $account = googleAccount(['expires_at' => now()->subMinute()]);

    app(GoogleDriveClient::class)->createDocument($account, 'Pack', 'x');
})->throws(GoogleAuthorizationException::class);

test('a Drive server error is not swallowed', function () {
    Http::fake(['www.googleapis.com/upload/drive/v3/files/*' => Http::response(['error' => 'boom'], 500)]);

    app(GoogleDriveClient::class)->replaceDocument(googleAccount(), 'doc-1', 'x');
})->throws(RequestException::class);
