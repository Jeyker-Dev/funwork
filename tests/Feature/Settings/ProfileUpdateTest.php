<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create(['name' => 'Alex Rivera']));

    $response = $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Back to projects')
        ->assertSee('Settings')
        ->assertSee('Profile')
        ->assertSee('Security')
        ->assertSee('Appearance')
        ->assertSee('Notifications')
        ->assertSee('Integrations')
        ->assertDontSee('Billing');

    $html = $response->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//header[@data-flux-header]')->length)->toBe(1)
        ->and($xpath->query('//aside[@data-flux-sidebar]')->length)->toBe(0)
        ->and($xpath->query('//header[@data-flux-header]//nav')->length)->toBe(0)
        ->and($xpath->query('//nav[@aria-label="Settings"]//a[@aria-current="page"]')->length)->toBe(1)
        ->and($html)->not->toContain('arrow_back')
        ->and($html)->not->toContain('arrow-left');
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('username', 'test_user')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('valid timezone can be saved', function () {
    $user = User::factory()->create(['username' => 'testuser', 'timezone' => 'UTC']);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('username', $user->username)
        ->set('email', $user->email)
        ->set('timezone', 'America/Argentina/Buenos_Aires')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->fresh()->timezone)->toEqual('America/Argentina/Buenos_Aires');
});

test('invalid timezone is rejected', function () {
    $user = User::factory()->create(['username' => 'testuser', 'timezone' => 'UTC']);

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('username', $user->username)
        ->set('email', $user->email)
        ->set('timezone', 'Invalid/Timezone_Name')
        ->call('updateProfileInformation');

    $response->assertHasErrors(['timezone']);

    expect($user->fresh()->timezone)->toEqual('UTC');
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('username', 'test_user')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'password')
        ->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('profile avatar falls back to initials and user can upload and remove a profile photo', function () {
    Storage::fake('local');
    config(['filesystems.default' => 'local']);

    $user = User::factory()->create([
        'name' => 'Alex Rivera',
        'username' => 'alex_rivera',
        'profile_photo_path' => null,
    ]);

    $this->actingAs($user);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Upload photo')
        ->assertSee('AR')
        ->assertSee('grid-cols-1 sm:grid-cols-2');

    $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

    Livewire::test('pages::settings.profile')
        ->set('photo', $file)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();
    $savedPath = $user->profile_photo_path;

    expect($savedPath)->not->toBeNull();
    Storage::disk('local')->assertExists($savedPath);

    Livewire::test('pages::settings.profile')
        ->assertSee('Remove')
        ->call('removePhoto')
        ->assertHasNoErrors();

    expect($user->fresh()->profile_photo_path)->toBeNull();
    Storage::disk('local')->assertMissing($savedPath);
});

test('danger zone renders in its own card with a secondary red-text delete account button', function () {
    $user = User::factory()->create();

    $html = $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Permanently delete your account and all of its projects. This action cannot be undone.')
        ->getContent();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $triggerButton = $xpath->query('//*[@data-test="delete-user-button"]')->item(0);

    expect($triggerButton)->not->toBeNull()
        ->and($triggerButton->getAttribute('class'))->toContain('text-danger')
        ->and($triggerButton->getAttribute('class'))->not->toContain('bg-red-500');
});

test('appearance settings page renders with the shared layout and theme options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('appearance.edit'))
        ->assertOk()
        ->assertSee('Back to projects')
        ->assertSee('Appearance')
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System')
        ->assertSee('bg-surface-card');
});
