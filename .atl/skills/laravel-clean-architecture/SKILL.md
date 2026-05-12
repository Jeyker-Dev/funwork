---
name: laravel-clean-architecture
description: >
  Clean architecture conventions for Laravel 13 + Livewire v4: SOLID principles,
  component decomposition, Actions, Services, Form Objects, and folder structure.
  Trigger: When creating new Livewire components, Blade views, controllers, models,
  or any PHP class in app/. Also when refactoring or organizing existing code.
license: Apache-2.0
metadata:
  author: gentleman-programming
  version: "1.0"
---

## When to Use

- Creating new Livewire components, controllers, or Blade views
- Deciding where to put business logic
- Refactoring fat components or controllers
- Organizing models, actions, services, or form objects
- Deciding between:inline component logic vs. extraction

## Core Principle: Single Responsibility

Every class, component, and view should do **one thing well**. When a component grows beyond 3-4 actions or 80 lines of PHP logic, extract responsibilities.

## Architecture Layers

```
app/
├── Livewire/
│   ├── Actions/          ← Single-purpose action classes
│   │   └── Logout.php
│   ├── Forms/             ← Livewire Form objects (validation + store)
│   │   ├── TaskForm.php
│   │   └── ProjectForm.php
│   └── (page components live in resources/views/pages/)
│
├── Models/                ← Eloquent models + relationships + scopes ONLY
│   ├── User.php
│   ├── Project.php
│   └── Task.php
│
├── Services/              ← Complex business logic (when needed)
│   └── TaskSortingService.php
│
├── Concerns/              ← Traits for shared model behavior
│   └── ProfileValidationRules.php
│
├── Http/                  ← Controllers (thin — delegate to Actions/Services)
│
└── Providers/              ← Service providers

resources/views/
├── pages/                 ← Full-page Livewire components
│   ├── auth/
│   ├── settings/
│   └── projects/
├── components/            ← Reusable Livewire components (small, focused)
│   ├── projects/
│   │   ├── ⚡create-project.blade.php
│   │   ├── ⚡project-finder.blade.php
│   │   └── tasks/
│   │       ├── ⚡create-task.blade.php
│   │       ├── ⚡slider-filters.blade.php
│   │       └── ⚡slider-pagination.blade.php
│   └── utilities/
│       └── ⚡search-input.blade.php
├── layouts/               ← Layouts (NOT Livewire components)
└── flux/                  ← Custom Flux icon overrides
```

## Decision Tree: Where Does Logic Go?

```
Need to validate + store a form?
  → Form Object (app/Livewire/Forms/)

Need a single discrete action (logout, enable 2FA, export)?
  → Action (app/Livewire/Actions/ or app/Actions/)

Need complex business logic (sorting, pricing, report generation)?
  → Service (app/Services/)

Need shared validation logic across multiple forms?
  → Concern/Trait (app/Concerns/)

Need Eloquent relationship, scope, accessor, or cast?
  → Model (app/Models/)

Need to coordinate multiple steps (create + notify + log)?
  → Component method calls Action(s)

Need UI state toggle (show/hide, tabs)?
  → Alpine x-data (local state, no server trip)
```

## Form Objects — The Primary Pattern

Form Objects are the **first place** to put form logic. Every form that validates and persists data should use a Form Object.

### When to Create a Form Object

| Create a Form Object when... | Keep logic in the Component when... |
|------------------------------|-------------------------------------|
| The form has 3+ fields | It's 1-2 fields with no complex validation |
| Validation rules are non-trivial | It's a simple toggle or action |
| The form creates/updates a model | The action is stateless (logout, redirect) |
| Multiple components share the same form | Only one component will ever use it |

### Form Object Convention

```php
// app/Livewire/Forms/TaskForm.php
namespace App\Livewire\Forms;

use App\Models\Task;
use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Form;

class TaskForm extends Form
{
    #[Validate('required')]
    public string $title = '';

    #[Validate('required')]
    public string $description = '';

    #[Validate('nullable|date_format:Y-m-d')]
    public string $dueDate = '';

    public function store(): void
    {
        $this->validate();

        Task::create([
            'title'       => $this->title,
            'description' => $this->description,
            'due_date'    => $this->dueDate,
            // ... all mapped fields
        ]);

        $this->reset();

        Flux::toast(
            heading: 'Task Created',
            text: 'The task has been successfully created.',
            variant: 'success',
            duration: 3000,
        );

        Flux::modals()->close();
    }
}
```

**Rules:**
1. Always type properties and provide defaults (`public string $title = '';`)
2. Use `#[Validate]` attributes, never `$rules` arrays
3. `store()` method: validate → create → reset → feedback (toast/modal close)
4. Field mapping: Form property names (`dueDate`) may differ from column names (`due_date`) — handle in `store()`
5. Always call `$this->reset()` after success to clear form state
6. Toast + modal close always go in the Form Object, not the Component

## Actions — Single-Purpose Classes

Actions are invokable classes that do **one thing**. They live in `app/Livewire/Actions/` (for things tied to Livewire) or `app/Actions/` (for general business logic).

### When to Create an Action

| Create an Action when... | Don't create one when... |
|--------------------------|--------------------------|
| It's a discrete operation with clear input/output | It's simple CRUD → use Form Object |
| Multiple components need the same action | Only one place will ever call it |
| The logic would clutter the component | It's just a redirect or state toggle |
| You need to inject services via constructor | It's validation + persistence → Form Object |

### Action Convention

```php
// app/Livewire/Actions/Logout.php
namespace App\Livewire\Actions;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class Logout
{
    public function __invoke()
    {
        Auth::guard('web')->logout();
        Session::invalidate();
        Session::regenerateToken();
        return redirect('/');
    }
}
```

```php
// Usage in a Blade template (stateless action)
<form method="POST" action="{{ route('logout') }}">
    @csrf
    <flux:menu.item as="button" type="submit">
        {{ __('Log out') }}
    </flux:menu.item>
</form>
```

**Rules:**
1. Actions are invokable (`__invoke()`)
2. One action = one responsibility
3. Inject dependencies via constructor, not method parameters
4. Return a response (redirect, etc.)
5. Keep them stateless — no stored properties

## Services — Complex Business Logic

Services are for **multi-step processes** that don't fit in a single Action or Form Object.

### When to Create a Service

| Create a Service when... | Don't create one when... |
|--------------------------|--------------------------|
| Logic involves 3+ steps/queries | It's 1-2 steps → Action or Form Object |
| Multiple models need coordination | It's just validation → Form Object |
| There are domain rules to enforce | It's a simple calculation → put it on the Model |
| You need to unit-test business logic in isolation | It's only used once, in one component |

### Service Convention

```php
// app/Services/TaskSortingService.php
namespace App\Services;

use App\Models\Project;
use App\Models\Task;

class TaskSortingService
{
    public function reorderTasks(Project $project, string $status, int $taskId, int $newPosition): void
    {
        // Complex multi-step logic: shift positions, validate bounds, etc.
        // This would be too much for a component method
    }

    public function getNextPosition(Project $project, string $status): int
    {
        return (Task::where('project_id', $project->id)
            ->where('status', $status)
            ->max('position') ?? 0) + 1;
    }
}
```

**Rules:**
1. Services are NOT invokable — they have multiple methods
2. Inject services into Components via constructor or `app()` — never `new`
3. Services should be stateless (no stored state between calls)
4. Services can be bound in the container if they have config dependencies

## Concerns/Traits — Shared Behavior

When multiple Form Objects or Components need the same validation rules:

```php
// app/Concerns/ProfileValidationRules.php
namespace App\Concerns;

trait ProfileValidationRules
{
    protected function profileRules(int $userId): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', "unique:users,email,{$userId}"],
        ];
    }
}
```

**Rules:**
1. Only use Concerns for **shared validation rules** or **shared model behavior**
2. Don't use Concerns for business logic — that goes in Services or Actions
3. Name them descriptively: `ProfileValidationRules`, not `Validatable`

## Component Decomposition — The Most Important Pattern

### Rule: If the Blade template has more than 3 sections, split it.

Each section becomes its own component. This keeps components **small, testable, and reusable**.

```
BEFORE (monolithic):
────────────────────────────────────────
resources/views/pages/projects/index.blade.php
  → Contains: header, sidebar, project list, create form, search
  → 200+ lines of Blade + 80+ lines of PHP

AFTER (decomposed):
────────────────────────────────────────
resources/views/pages/projects/⚡index.blade.php
  → Orchestrator: loads data, passes to children
  → ~30 lines of Blade + ~20 lines of PHP

resources/views/components/projects/
  ├── home/⚡header.blade.php           → Project header with actions
  ├── ⚡project-finder.blade.php       → Search/filter
  ├── ⚡create-project.blade.php       → Create modal + trigger button
  └── ⚡show-projects.blade.php        → Project grid/list
```

### Decomposition Checklist

When creating a new Livewire page or component:

1. **Identify sections** in the UI (header, list, filters, form, footer)
2. **Each section = a component** if it has interactivity or state
3. **Shared layouts** stay as Blade includes (`<x-layouts::app>`)
4. **Forms** always get their own Form Object
5. **Modals** always get their own component
6. **Reusable pieces** (search input, avatar) go in `components/utilities/`

### Communication Between Decomposed Components

| Method | When to Use | Example |
|--------|-------------|---------|
| `wire:model` | Parent → Child data binding | `<livewire:task-form :project-id="$projectId" />` |
| `$this->dispatch()` | Child → Parent notification | `$this->dispatch('task-created')` |
| `wire:listen` | Parent listens to child event | `<livewire:task-list wire:listen="task-created" />` |
| Props (`:prop-name`) | Pass static data down | `:project-id="$projectId"` |
| Session flash | Server-side flash message | `session()->flash('status', 'Created!')` |

### Never Put in a Component

| ❌ Don't | ✅ Instead, put it in |
|----------|------------------------|
| Raw DB queries | Model scope or Service |
| Complex business logic | Action or Service |
| Repeated validation rules | Concern/Trait |
| Email/Push notification logic | Action (+ queue) |
| External API calls | Service |

## Models — Lean and Focused

Models should contain **only**: attributes, casts, relationships, scopes, accessors, and mutators.

```php
// ✅ CORRECT — Model with relationships and a utility method
class User extends Authenticatable
{
    #[Fillable(['name', 'email', 'password'])]
    #[Hidden(['password', 'two_factor_secret', 'remember_token'])]

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}

// ❌ WRONG — Model doing too much
class User extends Authenticatable
{
    public function createProjectWithTasks(array $data): Project
    {
        // This belongs in a Service
    }

    public function sendWelcomeEmail(): void
    {
        // This belongs in an Action
    }
}
```

**Rules:**
1. Use `#[Fillable]` and `#[Hidden]` attributes (Laravel 13 style), not `$fillable` arrays
2. Relationships are always →methods← with return types
3. Accessors/mutators are fine on models (they're presentation logic tied to the model)
4. Business logic → Action or Service, NEVER on the model
5. Use `HasFactory` on all models that need test factories

## Named Routes — Always

```blade
{{-- ✅ CORRECT --}}
<flux:link :href="route('projects')" wire:navigate>Projects</flux:link>
<form method="POST" action="{{ route('login.store') }}">

{{-- ❌ WRONG --}}
<a href="/projects">Projects</a>
<form method="POST" action="/login">
```

Always use named routes. They're defined in `routes/web.php` and tested with `route('name')` in both Blade and tests.

## Naming Conventions

| Thing | Convention | Example |
|-------|------------|---------|
| Livewire page component | `⚡name.blade.php` in `pages/` | `pages/settings/⚡profile.blade.php` |
| Livewire reusable component | `⚡name.blade.php` in `components/` | `components/projects/⚡create-project.blade.php` |
| Component namespace | `directory.component-name` | `projects.create-project`, `pages::settings.profile` |
| Form Object class | `XxxForm.php` in `app/Livewire/Forms/` | `TaskForm.php`, `ProjectForm.php` |
| Action class | `XxxYyy.php` in `app/Livewire/Actions/` | `Logout.php`, `EnableTwoFactor.php` |
| Service class | `XxxService.php` in `app/Services/` | `TaskSortingService.php` |
| Concern/Trait | `XxxRules.php` in `app/Concerns/` | `ProfileValidationRules.php` |
| Livewire event | kebab-case past tense | `task-created`, `project-updated` |
| Route name | kebab-case with dots | `project.tasks`, `show.task` |

## Commands

```bash
# Create an anonymous Livewire component (preferred in this project)
# Creates the Blade file with the ⚡ naming convention
php artisan make:livewire ComponentName

# Create a Form Object manually
# app/Livewire/Forms/XxxForm.php

# Create an Action manually
# app/Livewire/Actions/XxxYyy.php or app/Actions/XxxYyy.php

# Create a Service manually
# app/Services/XxxService.php
```

## Resources

- **Livewire v4 docs**: https://livewire.laravel.com/docs
- **Laravel 13 docs**: https://laravel.com/docs
- **SOLID Principles**: Applied to Laravel — each layer has a single responsibility
- **Project skill**: `pest-laravel-testing` for testing these components
- **Project skill**: `livewire-flux` for Flux UI and Alpine patterns