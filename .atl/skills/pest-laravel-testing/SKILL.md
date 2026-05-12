---
name: pest-laravel-testing
description: >
  Testing patterns for Pest v4 + Laravel 13 + Livewire v4 + Fortify.
  Trigger: When writing tests, running Pest, testing Livewire components, or asserting
  Laravel feature/browser behavior.
license: Apache-2.0
metadata:
  author: gentleman-programming
  version: "1.0"
---

## When to Use

- Writing any test file under `tests/`
- Running or debugging `./vendor/bin/pest`
- Testing Livewire components or pages
- Testing Fortify auth flows (login, register, 2FA, password reset)
- Creating factories or seeders for test data
- Writing assertions for Eloquent models or HTTP responses

## Critical Patterns

### Test Structure

```
tests/
├── Pest.php                  # Global setup (RefreshDatabase for Feature, etc.)
├── TestCase.php              # Base class with helpers (skipUnlessFortifyFeature)
├── Feature/                  # Integration tests (uses RefreshDatabase via Pest.php)
│   ├── Auth/                 # Auth flow tests
│   ├── DashboardTest.php
│   └── Settings/             # Settings page tests
└── Unit/                     # Pure unit tests (no framework boot)
```

### Pest Syntax — NOT PHPUnit

```php
// ✅ CORRECT — Pest style
test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

it('can create a user', function () {
    User::factory()->create();
    expect(User::count())->toBe(1);
});

beforeEach(function () {
    $this->user = User::factory()->create();
});

// ❌ WRONG — Don't use PHPUnit style
class UserTest extends TestCase {
    public function test_something() { ... }
}
```

### Test Setup (Pest.php)

The project's `tests/Pest.php` configures:

```php
pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');
```

- Feature tests **automatically** get `RefreshDatabase` — no need to add the trait manually.
- Unit tests do NOT get `RefreshDatabase`.

### Custom Helpers

- `skipUnlessFortifyFeature(string $feature)` — defined in `TestCase.php`, skips test if a Fortify feature is disabled.

```php
beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});
```

### Writing Feature Tests

#### Auth Tests

```php
use App\Models\User;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));
    $response->assertOk();
});

test('users can authenticate', function () {
    $user = User::factory()->create();
    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();
    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);
    $response->assertSessionHasErrorsIn('email');
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post(route('logout'));
    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
```

#### Livewire Component Tests

Use `Livewire::test()` for testing anonymous Livewire components and full-page components:

```php
use Livewire\Livewire;

// Anonymous components use their view namespace
test('profile information can be updated', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = Livewire::test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();
    expect($user->refresh()->name)->toEqual('Test User');
});

// Testing form objects
test('form validation works', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = Livewire::test('pages::settings.delete-user-modal')
        ->set('password', 'wrong-password')
        ->call('deleteUser');

    $response->assertHasErrors(['password']);
});
```

**Key Livewire test APIs:**

| Method | Purpose |
|--------|---------|
| `Livewire::test('component::name')` | Mount a component for testing |
| `->set('property', $value)` | Set a public property |
| `->call('methodName')` | Call a component action |
| `->assertHasNoErrors()` | Assert no validation errors |
| `->assertHasErrors(['field'])` | Assert validation errors on fields |
| `->assertRedirect('/')` | Assert redirect response |
| `->assertSet('prop', $value)` | Assert property value |
| `->assertSee('text')` | Assert rendered output contains text |
| `->dispatch('event-name')` | Dispatch a Livewire event |

#### Route Name Convention

This project uses named routes consistently. Always use `route('name')` in tests:

```php
// ✅ CORRECT — named routes
$this->get(route('dashboard'));
$this->post(route('login.store'));

// ❌ WRONG — hardcoded URLs
$this->get('/dashboard');
$this->post('/login');
```

Key route names in this project:
- `login`, `login.store` — Fortify auth
- `register`, `register.store` — Fortify registration
- `logout` — Fortify logout
- `dashboard` — Dashboard (auth required)
- `projects` — Projects index (auth required)
- `projects.show`, `project.inbox`, `project.tasks` — Project sub-pages
- `profile.edit` — Settings profile

### Factories

The project uses `User::factory()` with these states:

```php
User::factory()->create();           // Standard user, password = 'password'
User::factory()->create(['name' => 'Custom Name']);
User::factory()->unverified()->create();    // Email not verified
User::factory()->withTwoFactor()->create(); // With 2FA enabled
```

The `UserFactory` uses `Hash::make('password')` as the default password — always use `'password'` as the test password.

### Testing with Livewire Form Objects

Form objects (`Livewire\Form`) are tested through their parent component:

```php
// Forms are attached to components, test the component
Livewire::test('pages::settings.profile')
    ->set('form.name', 'New Name')  // Use dot notation for form properties
    ->call('save');
```

### Flux Toasts & Modals in Tests

When testing components that use `Flux::toast()` or `Flux::modals()->close()`:

```php
// Toast assertions are not directly testable via HTTP.
// Test the underlying state change instead:
Livewire::test('component::name')
    ->set('form.title', 'Test')
    ->call('save')
    ->assertHasNoErrors()
    ->assertDispatched('task-created');  // Verify event was dispatched
```

## Commands

```bash
# Run all tests
./vendor/bin/pest

# Run specific test file
./vendor/bin/pest tests/Feature/Auth/AuthenticationTest.php

# Run tests matching a name
./vendor/bin/pest --filter="login"

# Run with coverage
./vendor/bin/pest --coverage

# Run only Feature tests
./vendor/bin/pest tests/Feature

# Run only Unit tests
./vendor/bin/pest tests/Unit

# Lint check (Pint — run before committing)
composer lint:check
```

## Resources

- **Pest v4 docs**: https://pestphp.com/docs
- **Livewire v4 testing**: https://livewire.laravel.com/docs/testing
- **Laravel testing**: https://laravel.com/docs/11.x/testing