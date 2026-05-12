---
name: livewire-flux
description: >
  Patterns and conventions for Livewire v4 + Flux/Flux Pro + Alpine v3 in the funwork project.
  Trigger: When creating or editing Livewire components, Blade views with Flux tags,
  Livewire form objects, Alpine.js interactivity, or any file in app/Livewire/ or resources/views/.
license: Apache-2.0
metadata:
  author: gentleman-programming
  version: "1.1"
---

## When to Use

- Creating or editing Livewire v4 components (anonymous or class-based)
- Writing Blade views that use `<flux:*>` components
- Building forms with Livewire Form objects
- Creating modals, toasts, or interactive UI with Flux
- Organizing components under `resources/views/`

## Critical Patterns

### Anonymous Livewire Components

This project uses **anonymous Livewire components** (inline PHP + Blade) as the PRIMARY pattern. The component class is defined directly in the Blade file using `new class extends Component`:

```blade
<?php

use Livewire\Component;
use App\Livewire\Forms\ProjectForm;

new class extends Component {
    public ProjectForm $form;
    public string $elementName = '';

    public function mount($elementName): void
    {
        $this->elementName = $elementName;
    }

    public function with(): array
    {
        return [
            'user' => auth()->user(),
        ];
    }

    public function save(): void
    {
        $this->form->store();
        $this->dispatch('project-created');
    };
}; ?>

<div>
    <!-- Blade template here -->
</div>
```

**Key conventions:**

| Convention | Pattern |
|------------|---------|
| Component class | `new class extends Component` inline in Blade |
| Form objects | Injected as public property: `public ProjectForm $form;` |
| Page title | `new #[Title('Page Title')] class extends Component` |
| Computed props | `#[Computed]` attribute on methods |
| Data passing | `with()` method returns data to the view |
| Events | `$this->dispatch('event-name')` to emit; `wire:listen` or `@script` to receive |
| Page components | Named with `::` namespace: `pages::settings.profile` |

### Component File Locations

```
resources/views/
├── pages/                         # Full-page Livewire components
│   ├── auth/                      # Auth pages (login, register, etc.)
│   │   ├── login.blade.php
│   │   ├── register.blade.php
│   │   └── ...
│   ├── settings/                  # Settings pages
│   │   ├── layout.blade.php       # Settings layout wrapper
│   │   └── ⚡profile.blade.php    # ⚡ prefix = Livewire anonymous component
│   └── projects/
│       └── ⚡index.blade.php
├── components/                    # Reusable Livewire components
│   ├── projects/
│   │   ├── ⚡create-project.blade.php
│   │   ├── ⚡project-finder.blade.php
│   │   ├── ⚡show-projects.blade.php
│   │   └── tasks/
│   │       ├── ⚡create-task.blade.php
│   │       ├── ⚡show-tasks.blade.php
│   │       └── ...
│   ├── auth/                      # Auth form wrappers
│   │   └── form.blade.php
│   └── utilities/
│       └── ⚡search-input.blade.php
├── layouts/                        # Layout files (NOT Livewire)
│   ├── app.blade.php
│   ├── home.blade.php
│   ├── auth.blade.php
│   └── app/
│       ├── sidebar.blade.php
│       ├── header.blade.php
│       └── body.blade.php
└── flux/                           # Custom Flux icon overrides
    ├── icon/
    └── navlist/
```

**The ⚡ prefix** (`⚡`) in Blade filenames indicates Livewire anonymous components. This is a Livewire convention — the `⚡` maps to the component class inline in the file.

### Livewire Form Objects

Form objects live in `app/Livewire/Forms/` and extend `Livewire\Form`:

```php
namespace App\Livewire\Forms;

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
            'title' => $this->title,
            'description' => $this->description,
            // ... mapped fields
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

**Form conventions:**

| Convention | Pattern |
|------------|---------|
| Validation | `#[Validate('rules')]` attributes on properties |
| Default values | Set on property declaration: `public string $title = '';` |
| Store method | Validates, creates, resets, then toasts |
| Toast notifications | `Flux::toast()` after successful operations |
| Modal closing | `Flux::modals()->close()` after form submission |
| Property reset | `$this->reset()` after successful store |

### Flux Component Reference

This project uses **Flux Pro** (`livewire/flux-pro`) alongside Flux Free components.

#### Layout Components

| Component | Usage |
|-----------|-------|
| `<flux:main>` | Main content wrapper: `<flux:main>` or `<flux:main class="...">` |
| `<flux:sidebar>` | Sticky sidebar with collapsible mobile support |
| `<flux:sidebar.header>` | Sidebar brand area |
| `<flux:sidebar.nav>` | Navigation section within sidebar |
| `<flux:sidebar.group>` | Grouped nav items: `<flux:sidebar.group :heading="__('Platform')">` |
| `<flux:sidebar.item>` | Nav link: `icon`, `:href`, `:current`, `wire:navigate` |
| `<flux:sidebar.collapse>` | Mobile collapse toggle |
| `<flux:sidebar.brand>` | Brand/logo area in sidebar |
| `<flux:header>` | Page header bar with slot-based content |
| `<flux:spacer>` | Flex spacer to push items apart |

#### Navigation Components

| Component | Usage |
|-----------|-------|
| `<flux:navbar>` | Horizontal navigation bar |
| `<flux:link>` | Text link: `<flux:link :href="route('register')" wire:navigate>` |

#### Form Components

| Component | Key Props | Notes |
|-----------|-----------|-------|
| `<flux:input>` | `label`, `placeholder`, `type`, `wire:model`, `required`, `autofocus`, `viewable` | `viewable` toggles password visibility |
| `<flux:textarea>` | `label`, `placeholder`, `wire:model` | |
| `<flux:select>` | `label`, `placeholder`, `variant="listbox"`, `wire:model` | Always use `variant="listbox"` for dropdowns |
| `<flux:select.option>` | `value` (optional) | Text content becomes label |
| `<flux:checkbox>` | `name`, `:label`, `:checked` | |
| `<flux:radio>` | `value`, `icon` | Used inside `<flux:radio.group>` |
| `<flux:radio.group>` | `variant="segmented"`, `x-data`, `x-model`, `wire:model` | |
| `<flux:input type="date">` | `label`, `wire:model` | Date picker field |

#### Feedback Components

| Component | Key Props | Notes |
|-----------|-----------|-------|
| `<flux:toast />` | — | Must be wrapped in `@persist('toast')` block |
| `<flux:heading>` | `size`, `class` | Text levels: `size="lg"`, default |
| `<flux:text>` | `class` | Body text, supports `class="mt-2"` |
| `<flux:badge>` | `size`, `color`, `class` | Status badges: `color="orange"` |
| `<flux:button>` | `variant`, `icon`, `type`, `class`, `href` | Variants: `primary`, `ghost` |

#### Modal Components

| Component | Key Props | Notes |
|-----------|-----------|-------|
| `<flux:modal>` | `name`, `class`, `flyout` (optional) | Named modals activated by triggers |
| `<flux:modal.trigger>` | `name` | Must match modal `name` attribute |
| `<flux:modal.close>` | — | Closes the modal, wraps a `<flux:button>` |

**Modal pattern:**

```blade
<flux:modal.trigger name="create-project">
    <flux:button icon="plus">New project</flux:button>
</flux:modal.trigger>

<flux:modal name="create-project" class="w-72 md:w-full">
    <form wire:submit.prevent="save">
        <!-- Form fields -->
        <flux:modal.close>
            <flux:button>Cancel</flux:button>
        </flux:modal.close>
        <flux:spacer />
        <flux:button type="submit" variant="primary">Create</flux:button>
    </form>
</flux:modal>
```

#### Toast Pattern

Toast notifications use PHP-side `Flux::toast()` in form objects and require a persisted toast container:

```blade
{{-- In the component's Blade template --}}
@persist('toast')
    <flux:toast />
@endpersist
```

```php
// In the Form object or Component
Flux::toast(
    heading: 'Task Created',
    text: 'The task has been successfully created.',
    variant: 'success',
    duration: 3000,
);
```

Toast variants: `success`, `error`, `warning`, `info`.

#### Dropdown & Menu Components

| Component | Usage |
|-----------|-------|
| `<flux:dropdown>` | `position="top"`, `align="end"` |
| `<flux:profile>` | `:initials`, `icon-trailing="chevron-down"` |
| `<flux:menu>` | Menu container |
| `<flux:menu.radio.group>` | Grouped menu items |
| `<flux:menu.item>` | Menu item: `:href`, `icon`, `as="button"`, `type="submit"` |
| `<flux:menu.separator>` | Visual separator |

#### Avatar & Icons

| Component | Key Props |
|-----------|-----------|
| `<flux:avatar>` | `:name`, `:initials`, `tooltip`, `src`, `size="sm"` |
| `<flux:icon.plus>` | `variant="solid"`, `class="..."` |
| `<flux:icon.plus-circle>` | `class="..."` |
| `<flux:icon.arrows-pointing-out>` | Custom attributes like `x-sort:handle` |
| `<flux:icon.calendar-days>` | `class="..."` |
| `<flux:icon.clock>` | - |
| `<flux:icon.cog-6-tooth>` | `variant="ghost"` |
| `<flux:icon.wrench>` | Used as radio icon |

Custom icon overrides live in `resources/views/flux/icon/`.

#### Card & Container

| Component | Key Props |
|-----------|-----------|
| `<flux:card>` | Wrapper card, used for task/project items |
| `<flux:field>` | Form field wrapper: wraps `flux:label`, `flux:input`, `flux:error` |
| `<flux:label>` | `class` for custom styling |
| `<flux:error>` | `name` attribute matches input name |

### Route Integration with Livewire

Routes use `Route::livewire()` for full-page Livewire components:

```php
// routes/web.php
Route::livewire('/projects', 'pages::projects.index')->name('projects');
```

For anonymous components in the `pages` directory, the component name follows the namespace pattern: `pages::directory.component-name`.

Regular Blade views use `Route::view()`:

```php
Route::view('projects/{project}', 'app.projects.show')->name('projects.show');
```

### Wire Directives in Blade

| Directive | Purpose |
|-----------|---------|
| `wire:submit.prevent="save"` | Submit form, prevent default, call `save()` |
| `wire:model="property"` | Two-way bind to component property |
| `wire:model="form.field"` | Two-way bind to form object field |
| `wire:click.prevent="method"` | Click with prevent default |
| `wire:navigate` | Livewire SPA navigation (use on links) |
| `@persist('toast')` | Persist element across navigations |

### Mutation Patterns

When a component dispatches an event after mutation, parent components listen and re-render:

```blade
{{-- Child component dispatches --}}
$this->dispatch('task-created');

{{-- Parent Blade template listens --}}
<x-projects::tasks.create-task :project-id="$projectId" wire:listen="task-created" />
```

### Auth Form Layout

Auth pages use a shared layout and form wrapper:

```blade
<x-layouts::auth :title="__('Log in')">
    <x-auth.form method="POST" action="{{ route('login') }}">
        <!-- Form fields -->
    </x-auth.form>
</x-layouts::auth>
```

### Settings Page Layout

Settings pages use a consistent layout component:

```blade
<x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
    <!-- Settings content -->
</x-pages::settings.layout>
```

## Alpine v3 Integration

Alpine v3 is bundled with Livewire v4 (`@alpinejs/sort` is an explicit dependency). Use Alpine for **client-side interactivity** that doesn't need server round-trips — toggles, show/hide, clipboard, conditional UI states.

### When to Use Alpine vs Livewire

| Use Livewire (`wire:*`) when | Use Alpine (`x-*`) when |
|-------------------------------|--------------------------|
| Data needs server persistence | Toggle/show/hide UI only |
| Form submission | Local validation feedback (e.g. disable button) |
| Database queries | Clipboard, drag, animation |
| Auth checks | Theme/appearance switching |
| Muchos datos del servidor | Transiciones CSS conditionales |

### Key Integration Patterns

#### `$wire` — Access Livewire component state from Alpine

Access the Livewire component's public properties and methods from Alpine:

```blade
{{-- Disable button until 6 characters entered --}}
<flux:button
    variant="primary"
    wire:click="confirmTwoFactor"
    x-bind:disabled="$wire.code.length < 6"
>
    {{ __('Confirm') }}
</flux:button>
```

**Rules for `$wire`:**
- `$wire.property` reads a Livewire public property (reactive)
- `$wire.method()` calls a Livewire action (triggers round-trip)
- Use `x-bind:disabled`, `x-bind:class`, `x-bind:style` with `$wire` — NOT `x-model` for Livewire props
- `$wire` is only available inside Livewire component templates

#### `x-data` Inline — Local Alpine State

For component-scoped state that doesn't belong on the server:

```blade
{{-- Toggle between code and recovery input --}}
<div x-data="{ showRecoveryInput: false }">
    <div x-show="!showRecoveryInput">
        {{-- Code input --}}
    </div>
    <div x-show="showRecoveryInput">
        {{-- Recovery input --}}
    </div>
    <span x-show="!showRecoveryInput" @click="showRecoveryInput = true">
        {{ __('login using a recovery code') }}
    </span>
    <span x-show="showRecoveryInput" @click="showRecoveryInput = false">
        {{ __('login using an authentication code') }}
    </span>
</div>
```

#### Clipboard with Alpine

```blade
<div x-data="{
    copied: false,
    async copy() {
        try {
            await navigator.clipboard.writeText('{{ $manualSetupKey }}');
            this.copied = true;
            setTimeout(() => this.copied = false, 1500);
        } catch (e) {
            console.warn('Could not copy to clipboard');
        }
    }
}">
    <button @click="copy()">
        <flux:icon.document-duplicate x-show="!copied" variant="outline"></flux:icon>
        <flux:icon.check x-show="copied" variant="solid" class="text-green-500"></flux:icon>
    </button>
</div>
```

#### `$flux.appearance` — Dark Mode / Theme Reactivity

Flux exposes `$flux.appearance` for theme toggling. Use it in Alpine expressions:

```blade
{{-- Appearance switcher --}}
<flux:radio.group x-data variant="segmented" x-model="$flux.appearance">
    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
</flux:radio.group>
```

For conditional styles based on dark mode:

```blade
<div :style="($flux.appearance === 'dark' || ($flux.appearance === 'system' && $flux.dark)) ? 'filter: invert(1) brightness(1.5)' : ''">
```

#### `@this.on()` — Listen to Livewire Events in Alpine

The `action-message` component uses `@this.on()` to listen for Livewire events and trigger Alpine state:

```blade
{{-- Show a message for 2 seconds when a Livewire event fires --}}
<div
    x-data="{ shown: false, timeout: null }"
    x-init="@this.on('profile-updated', () => {
        clearTimeout(timeout);
        shown = true;
        timeout = setTimeout(() => { shown = false }, 2000);
    })"
    x-show.transition.out.opacity.duration.1500ms="shown"
    x-transition:leave.opacity.duration.1500ms
    style="display: none"
>
    {{ __('Saved.') }}
</div>
```

Usage in the parent component: `$this->dispatch('profile-updated', name: $user->name);`

#### `wire:cloak` — Prevent Flash of Unstyled Content

Add `wire:cloak` on elements that rely on Livewire data to render correctly. Hides them until Livewire initializes:

```blade
<div wire:cloak>
    {{-- Content that depends on server data --}}
</div>
```

#### `@persist` — Keep Elements Across Navigation

Used with Flux toasts. Persists a DOM element across Livewire navigations:

```blade
@persist('toast')
    <flux:toast />
@endpersist
```

#### `$flux.dark` — Check Dark Mode State

Boolean that's `true` when dark mode is active:

```blade
<div :class="$flux.dark ? 'bg-gray-900' : 'bg-white'">
```

### Anti-Patterns

| ❌ Don't | ✅ Do |
|----------|-------|
| Use `x-model` to bind to a Livewire property | Use `wire:model` for server-bound data |
| Duplicate server state in `x-data` | Use `$wire.prop` to read Livewire state from Alpine |
| Use Alpine for form submission | Use `wire:submit` or `wire:click` for server actions |
| Put complex logic in `x-data` inline | Extract to `app/Livewire/Actions/` or a service |
| Forget `wire:cloak` on server-dependent UI | Add `wire:cloak` to prevent FOUC |

## Commands

```bash
# Create a new anonymous Livewire component
php artisan make:livewire ComponentName

# Clear Livewire cache
php artisan livewire:clear-cache

# Run the dev server (Livewire + Vite)
composer dev
```

## Resources

- **Livewire v4 docs**: https://livewire.laravel.com/docs
- **Flux docs**: https://fluxui.dev/docs
- **Project models**: `App\Models\User`, `App\Models\Project`, `App\Models\Task`