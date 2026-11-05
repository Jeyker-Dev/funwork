<?php

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;

const DRIVE_FILE_SCOPE = 'https://www.googleapis.com/auth/drive.file';

beforeEach(function () {
    config([
        'services.google.client_id' => 'test-client-id',
        'services.google.client_secret' => 'test-client-secret',
        'services.google.redirect' => '/auth/google/callback',
    ]);
});

function fakeGoogleUser(array $overrides = []): void
{
    $googleUser = (new SocialiteUser)->map([
        'id' => $overrides['id'] ?? 'google-123',
        'email' => $overrides['email'] ?? 'owner@gmail.com',
        'name' => 'Owner',
    ])->setToken($overrides['token'] ?? 'access-token')
        ->setRefreshToken(array_key_exists('refresh', $overrides) ? $overrides['refresh'] : 'refresh-token')
        ->setExpiresIn(3599)
        ->setApprovedScopes($overrides['scopes'] ?? ['openid', 'email', DRIVE_FILE_SCOPE]);

    $provider = Mockery::mock(GoogleProvider::class);
    $provider->shouldReceive('user')->andReturn($googleUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

test('connecting sends the user to Google asking for offline drive.file access', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('google.redirect'));

    $location = $response->assertRedirect()->headers->get('Location');

    expect($location)
        ->toStartWith('https://accounts.google.com/o/oauth2/auth')
        ->toContain(urlencode(DRIVE_FILE_SCOPE))
        ->toContain('access_type=offline')
        ->toContain('prompt=consent');
});

test('the callback stores the Google account with encrypted tokens', function () {
    $user = User::factory()->create();
    fakeGoogleUser();

    $this->actingAs($user)
        ->get(route('google.callback'))
        ->assertRedirect(route('integrations.edit'));

    $account = $user->fresh()->googleAccount;

    expect($account)
        ->google_id->toBe('google-123')
        ->email->toBe('owner@gmail.com')
        ->access_token->toBe('access-token')
        ->refresh_token->toBe('refresh-token')
        ->and($account->expires_at->isFuture())->toBeTrue();

    $raw = DB::table('google_accounts')->where('id', $account->id)->first();
    expect($raw->access_token)->not->toBe('access-token')
        ->and($raw->refresh_token)->not->toBe('refresh-token');
});

test('reconnecting keeps the stored refresh token when Google does not send a new one', function () {
    $user = User::factory()->create();
    GoogleAccount::create([
        'user_id' => $user->id,
        'google_id' => 'google-123',
        'email' => 'owner@gmail.com',
        'access_token' => 'old-access',
        'refresh_token' => 'old-refresh',
        'expires_at' => now()->subHour(),
    ]);
    fakeGoogleUser(['token' => 'new-access', 'refresh' => null]);

    $this->actingAs($user)->get(route('google.callback'));

    expect(GoogleAccount::where('user_id', $user->id)->count())->toBe(1)
        ->and($user->fresh()->googleAccount)
        ->access_token->toBe('new-access')
        ->refresh_token->toBe('old-refresh');
});

test('the callback rejects a connection without Drive access', function () {
    $user = User::factory()->create();
    fakeGoogleUser(['scopes' => ['openid', 'email']]);

    $this->actingAs($user)
        ->get(route('google.callback'))
        ->assertRedirect(route('integrations.edit'))
        ->assertSessionHas('google_error');

    expect($user->fresh()->googleAccount)->toBeNull();
});

test('the callback handles a user who cancels on Google', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('google.callback', ['error' => 'access_denied']))
        ->assertRedirect(route('integrations.edit'))
        ->assertSessionHas('google_error');

    expect($user->fresh()->googleAccount)->toBeNull();
});

test('the callback returns to the page that started the connection', function () {
    $user = User::factory()->create();
    fakeGoogleUser();

    $this->actingAs($user)
        ->withSession(['google_return_to' => '/projects/1'])
        ->get(route('google.callback'))
        ->assertRedirect('/projects/1');
});

test('the callback ignores a return target outside the site', function (string $target) {
    $user = User::factory()->create();
    fakeGoogleUser();

    $this->actingAs($user)
        ->withSession(['google_return_to' => $target])
        ->get(route('google.callback'))
        ->assertRedirect(route('integrations.edit'));
})->with([
    'absolute url' => 'https://evil.example',
    'protocol relative' => '//evil.example',
    'backslash' => '/\\evil.example',
]);

test('the integrations page offers to connect Google', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('integrations.edit'))
        ->assertOk()
        ->assertSee(route('google.redirect'));
});

test('the integrations page shows the connected account and disconnects it', function () {
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response()]);

    $user = User::factory()->create();
    GoogleAccount::create([
        'user_id' => $user->id,
        'google_id' => 'google-123',
        'email' => 'owner@gmail.com',
        'access_token' => 'access-token',
        'refresh_token' => 'refresh-token',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs($user);

    Livewire::test('pages::settings.integrations')
        ->assertSee('owner@gmail.com')
        ->call('disconnectGoogle')
        ->assertDontSee('owner@gmail.com');

    expect($user->fresh()->googleAccount)->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/revoke'
        && $request['token'] === 'refresh-token');
});

test('guests cannot start a Google connection', function () {
    $this->get(route('google.redirect'))->assertRedirect(route('login'));
});
