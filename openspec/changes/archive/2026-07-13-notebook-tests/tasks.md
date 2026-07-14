# Tasks: notebook-tests

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~250-350 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | single PR |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Add Notebook Test Coverage | PR 1 | Base branch: main; independent isolated tests |

## Phase 1: Setup

- [x] 1.1 Review and update `database/factories/NotebookFactory.php` to ensure it supports and generates `project_id` and `created_by` relationships.

## Phase 2: Model Integration

- [x] 2.1 Create `tests/Feature/Models/NotebookTest.php` to verify `Notebook` relationships (`project`, `creator`).
- [x] 2.2 Verify `Notebook` mass assignment and fillable attributes in `tests/Feature/Models/NotebookTest.php`.

## Phase 3: Form Validation and Persistence

- [x] 3.1 Create `tests/Feature/Livewire/Forms/NotebookFormTest.php`.
- [x] 3.2 Add test using `Livewire::test()` (mounting `components.projects.notebooks.create-notebook`) to assert missing `project_id` fails validation.
- [x] 3.3 Add test using `Livewire::test()` to assert missing `created_by` fails validation.
- [x] 3.4 Add test asserting valid form data successfully persists the notebook, dispatches the `notebook-created` event, resets the form, and triggers modal close.

## Phase 4: UI Component Rendering and State

- [x] 4.1 Create `tests/Feature/Livewire/Projects/Notebooks/IndexTest.php` to verify the main page component mounts successfully.
- [x] 4.2 Create `tests/Feature/Livewire/Projects/Notebooks/ShowNotebooksTest.php` to verify rendering of the list when notebooks exist.
- [x] 4.3 Add a test to `ShowNotebooksTest.php` asserting empty state rendering ("No Notebooks, Yet.") when the project has zero notebooks.
- [x] 4.4 Create `tests/Feature/Livewire/Projects/Notebooks/CreateNotebookTest.php` to assert proper rendering of the creation modal/form component itself.
