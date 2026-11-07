# Tooling

Tools enforce what can be measured. When a tool is installed, run it and obey
its output. When it is missing, propose installing it; do not install without
asking.

| Tool | Package | Enforces |
|---|---|---|
| Pint | `laravel/pint` | Code style |
| Larastan | `larastan/larastan` | Types are real, so "trust the types" is safe |
| Rector | `rector/rector` + `driftingly/rector-laravel` | Mechanical upgrades to modern PHP (`match`, readonly, enums) |
| PHP Insights | `nunomaduro/phpinsights` | Complexity, class and method length, coupling |
| Pest arch | `pestphp/pest` | Layer and naming conventions |
| Pest mutate | `pestphp/pest` | Tests actually protect behavior |

## Commands

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
vendor/bin/rector process --dry-run
php artisan insights --no-interaction
vendor/bin/pest --mutate --covered-only
```

Run Rector without `--dry-run` only after the tests pass, then run the tests
again.

## Architecture tests

Put them in `tests/ArchTest.php`. The Laravel preset already limits controllers
to the seven resource methods and enforces suffixes.

```php
arch()->preset()->php();
arch()->preset()->security();
arch()->preset()->laravel();

arch('actions are invokable')
    ->expect('App\Actions')
    ->toHaveMethod('__invoke');

arch('models do not depend on actions or services')
    ->expect('App\Models')
    ->not->toUse(['App\Actions', 'App\Services']);
```

## Mutation tests

Declare what each test file covers so mutations target that class:

```php
covers(CancelOrder::class);
```
