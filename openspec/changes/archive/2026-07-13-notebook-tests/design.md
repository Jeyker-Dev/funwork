# Design: notebook-tests

## Technical Approach

Use Pest v4 for comprehensive test coverage of the Notebook module. Tests will mirror the Livewire v4 component structure and follow the project's testing conventions. Feature tests will use the `RefreshDatabase` trait automatically provided by Pest's configuration. Livewire component testing will leverage `Livewire::test()` with dot notation for testing form objects natively within the component context, and using Pest's expectation API to verify domain logic and UI state rendering.

## Architecture Decisions

### Decision: Mocking vs DB Interactions

**Choice**: Direct Database Interactions with `RefreshDatabase`.
**Alternatives considered**: Mocking the Notebook model or Eloquent relationships.
**Rationale**: The project uses Pest with the `RefreshDatabase` trait globally configured for Feature tests. This pattern provides higher confidence that database constraints (like foreign keys for `project_id` and `created_by`) and Eloquent relationships work properly in integration, avoiding the fragility of mocked models.

### Decision: Testing Form Objects via Parent Component

**Choice**: Form validations and persistence logic will be tested by mounting the Livewire components that include the form (e.g., `projects.notebooks.create-notebook`) using `Livewire::test()`.
**Alternatives considered**: Instantiating the `NotebookForm` class directly and calling its methods.
**Rationale**: Testing through the component mirrors real usage, validates Livewire data binding (using `form.field` dot notation), and allows assertion of side effects like `Flux::toast` (via event dispatch verification) and `Flux::modals()->close()`.

### Decision: Anonymous Livewire Component Testing

**Choice**: Use view namespace resolution (e.g. `Livewire::test('pages::projects.notebooks.index')` or `Livewire::test('components.projects.notebooks.create-notebook')`) to mount and test anonymous components.
**Alternatives considered**: Extracting the inline PHP classes to dedicated component classes for testing.
**Rationale**: The project heavily relies on the ⚡ prefix anonymous component pattern. Refactoring to class-based components just for testing would violate established architectural conventions. `Livewire::test` natively supports testing these anonymous components using their view path.

## Data Flow

    Test Setup (Factory) ──→ Livewire::test(Component) ──→ NotebookForm 
                                                                │
                                                                ▼
    Database Assertions ◀── Event Assertions ◀──────────── Notebook Model

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `tests/Feature/Models/NotebookTest.php` | Create | Unit-style feature tests for Notebook model relationships and fillable attributes. |
| `tests/Feature/Livewire/Forms/NotebookFormTest.php` | Create | Tests for `NotebookForm` validation rules and persistence via a real component (or dummy component if testing form alone). |
| `tests/Feature/Livewire/Projects/Notebooks/IndexTest.php` | Create | Tests for the `⚡index.blade.php` notebook page component. |
| `tests/Feature/Livewire/Projects/Notebooks/CreateNotebookTest.php` | Create | Tests for the `⚡create-notebook.blade.php` component. |
| `tests/Feature/Livewire/Projects/Notebooks/ShowNotebooksTest.php` | Create | Tests for the `⚡show-notebooks.blade.php` and `⚡filters.blade.php` components. |

## Interfaces / Contracts

No new interfaces or API contracts are required, as this change introduces test coverage for existing functionality. We will use the `NotebookFactory` to generate test data.

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Integration | Model | `NotebookTest.php` asserting Eloquent relations (`project()`, `creator()`) and attributes. |
| Integration | Form Object | `NotebookFormTest.php` setting `form.title` etc. and asserting `assertHasErrors()` or `assertHasNoErrors()`. |
| Integration | Livewire UI | Testing anonymous components using `Livewire::test()` to check rendered empty states ("No Notebooks, Yet.") and event emissions (e.g. `notebook-created`). |

## Migration / Rollout

No migration required.

## Open Questions

- [ ] Does a `NotebookFactory` fully support all attributes (e.g. `project_id`, `created_by`), or will it need to be updated alongside the tests?
