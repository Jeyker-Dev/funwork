# Pest Conventions

Pest syntax comes from Laravel Boost. These are decisions only; the strategy is
in the `testing-strategy` skill.

- Functional Pest style only (`test()`, `it()`, `expect()`). No PHPUnit test
  classes.
- Feature tests go through routes and Livewire components; Unit tests do not
  boot the framework.
- Form Objects are tested through the component that owns them:

```php
Livewire::test('pages::settings.profile')
    ->set('form.name', 'New Name')
    ->call('save')
    ->assertHasNoErrors();
```

- Do not assert toasts or modal closing. Assert the state change and the
  dispatched event:

```php
Livewire::test('projects.create-task')
    ->set('form.title', 'Write docs')
    ->call('save')
    ->assertDispatched('task-created');

expect(Task::where('title', 'Write docs')->exists())->toBeTrue();
```

- Use model factories and their states for test data; never insert rows by
  hand.
- Call routes by name: `$this->get(route('dashboard'))`.
