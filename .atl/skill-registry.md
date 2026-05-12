# Skill Registry — funwork

Auto-generated registry of project-specific skills and conventions.

## Project Skills

| Skill | Description | Trigger | File |
|-------|-------------|---------|------|
| `pest-laravel-testing` | Testing patterns for Pest v4 + Laravel 13 + Livewire v4 | Writing tests, running Pest, testing auth or Livewire components | [SKILL.md](.atl/skills/pest-laravel-testing/SKILL.md) |
| `livewire-flux` | Patterns for Livewire v4 + Flux/Flux Pro + Alpine v3 UI | Creating/editing Livewire components, Blade views, form objects, modals, toasts, Alpine interactivity | [SKILL.md](.atl/skills/livewire-flux/SKILL.md) |
| `laravel-clean-architecture` | SOLID, Actions, Services, Form Objects, component decomposition | Creating new components/controllers/models, refactoring, organizing code, deciding where logic goes | [SKILL.md](.atl/skills/laravel-clean-architecture/SKILL.md) |

## Global Skills (from opencode config)

| Skill | Description |
|-------|-------------|
| `branch-pr` | PR creation workflow following issue-first enforcement |
| `chained-pr` | Split large changes into chained/stacked PRs |
| `cognitive-doc-design` | Design docs that reduce cognitive load |
| `comment-writer` | Write warm, direct comments for PRs/issues |
| `go-testing` | Go testing patterns (not applicable to this project) |
| `issue-creation` | Issue creation workflow |
| `judgment-day` | Parallel adversarial review protocol |
| `sdd-*` | Spec-Driven Development phases |
| `skill-creator` | Create new AI agent skills |
| `skill-registry` | Create/update this skill registry |
| `work-unit-commits` | Structure commits as deliverable work units |

## Compact Rules

### pest-laravel-testing
- Use Pest syntax (`test()`, `it()`, `beforeEach()`), NEVER PHPUnit class syntax
- Feature tests auto-get RefreshDatabase via Pest.php — do NOT add the trait manually
- Use `skipUnlessFortifyFeature()` from TestCase for Fortify feature-gated tests
- Use named routes: `route('dashboard')`, NOT hardcoded URLs
- Default test password is `'password'` (from UserFactory)
- Test Livewire components with `Livewire::test('component::name')`
- Test form objects via their parent component with dot notation: `->set('form.field', $value)`
- Use `Flux::toast()` assertions indirectly — verify state changes or dispatched events instead

### livewire-flux
- Use anonymous Livewire components (`new class extends Component`) as the PRIMARY pattern
- ⚡ prefix in Blade filenames = anonymous Livewire component
- Form objects live in `app/Livewire/Forms/` and extend `Livewire\Form`
- Use `#[Validate('rules')]` attributes on form properties, not `$rules` array
- Full-page Livewire routes: `Route::livewire('/path', 'pages::namespace.component')`
- Always use `wire:navigate` on internal links for SPA navigation
- Modal pattern: `<flux:modal.trigger name="x">` → `<flux:modal name="x">`
- Toast pattern: `Flux::toast()` in PHP + `@persist('toast')<flux:toast />@endpersist` in Blade
- Use `Flux::modals()->close()` after successful form submission
- Select dropdowns: always add `variant="listbox"` to `<flux:select>`
- Settings pages: `<x-pages::settings.layout :heading="" :subheading="">`
- Auth pages: `<x-layouts::auth :title="">` + `<x-auth.form method="POST" action="">`

### laravel-clean-architecture
- **Form Objects FIRST** for any form with 3+ fields → `app/Livewire/Forms/XxxForm.php`
- **Actions** for single discrete operations → `app/Livewire/Actions/` or `app/Actions/`
- **Services** for complex multi-step business logic → `app/Services/XxxService.php`
- **Concerns/Traits** for shared validation rules → `app/Concerns/`
- **Models** are lean: relationships, scopes, casts, accessors ONLY — no business logic
- **Decompose components**: if Blade has 3+ sections, each section becomes its own component
- Modal = separate component, Form = separate Form Object, Action = separate Action class
- Use `#[Fillable]` and `#[Hidden]` attributes (Laravel 13), not `$fillable` arrays
- Named routes ALWAYS: `route('name')`, never hardcoded URLs
- Events: kebab-case past tense (`task-created`, `project-updated`)
- Component namespace convention: `directory.component-name` for components, `pages::namespace.component` for pages

## Stack Summary

- **Framework:** Laravel 13 (Livewire starter kit)
- **PHP:** ^8.3
- **Frontend:** Vite + TailwindCSS
- **UI:** Livewire v4 + Flux Free + Flux Pro
- **Auth:** Laravel Fortify
- **Testing:** Pest v4
- **Linting:** Laravel Pint