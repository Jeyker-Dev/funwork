# Proposal: Notebook Module Test Coverage

## Intent

Add comprehensive Pest v4 test coverage for the Notebook module. Currently, only `NotebookPageEditorTest` exists, leaving the core Model, Form Object, and UI components untested. This change ensures that all notebook CRUD operations, validation rules, UI states, and domain invariants are thoroughly verified, reducing regression risk.

## Scope

### In Scope
- Unit/Feature tests for `app/Models/Notebook.php` (relations, fillable attributes).
- Feature tests for `app/Livewire/Forms/NotebookForm.php` (validation rules, persistence logic, edge cases like missing `project_id` or `created_by`).
- Feature tests for Livewire anonymous components: `⚡index.blade.php`, `create-notebook`, `show-notebooks`, and `filters`.
- Assertion of successful flows, validation errors, and empty UI states (e.g., rendering "No Notebooks, Yet.").

### Out of Scope
- Adding new features or modifying existing behavior of the Notebook module.
- Testing `NotebookPageEditor` (already covered).

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- None

## Approach

Use Pest v4 alongside Livewire's testing helpers (`Livewire::test()`). 
1. **Model Tests**: Test factories, attributes, and relations (`project`, `creator`).
2. **Form Object Tests**: Test via the parent component, set form fields using dot notation (`form.title`), call `store`/`save`, and assert `->assertHasErrors()` or `->assertHasNoErrors()`. Ensure edge cases for `project_id` and `created_by` fail validation when missing.
3. **Component Tests**: Mount the anonymous components via `Livewire::test()`. Assert rendered text like "No Notebooks, Yet." when the state is empty, and verify event dispatching (e.g., `notebook-created`).

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `tests/Feature/Models/NotebookTest.php` | New | Model relationship and attribute tests |
| `tests/Feature/Livewire/Forms/NotebookFormTest.php` | New | Form object validation and persistence tests |
| `tests/Feature/Livewire/Projects/Notebooks/` | New | Component tests for index, create, show, and filters |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Missing edge cases for Livewire anonymous components | Low | Thoroughly review the components for internal state and emitted events, using `Livewire::test` event assertions. |

## Rollback Plan

If the new tests cause CI failures or are flaky, the added test files can be safely deleted or skipped (`->skip()`) until fixed, as they do not modify production code.

## Dependencies

- Pest v4 and Livewire testing helpers

## Success Criteria

- [ ] All components and classes listed in the scope have dedicated Pest test files.
- [ ] Tests cover happy paths, validation failures, and edge cases (empty states).
- [ ] Test suite passes cleanly via `./vendor/bin/pest`.
