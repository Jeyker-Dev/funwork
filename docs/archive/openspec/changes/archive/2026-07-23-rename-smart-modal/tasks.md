# Tasks: Rename Smart Modal

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 150-250 |
| 400-line budget risk | Low |
| Chained PRs recommended | No |
| Suggested split | Not needed |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

### Suggested Work Units

Not needed (Low risk).

## Phase 1: Foundation (Form Object)

- [x] 1.1 Create `app/Livewire/Forms/RenameItemForm.php` as a Livewire form object.
- [x] 1.2 Implement validation logic in `RenameItemForm` with a custom rule to ensure the name is unique against both `ProjectFile` and `Folder` models in the same parent directory.
- [x] 1.3 Add an `updateItem()` method in `RenameItemForm` that applies the rename operation and combines base name and extension for files.

## Phase 2: Core Implementation (Modal Component)

- [x] 2.1 Create `app/Livewire/Projects/Files/RenameItemModal.php` Livewire component.
- [x] 2.2 Implement `mount(ProjectFile|Folder $item)` to authorize via `update` policy and initialize the form object.
- [x] 2.3 Implement logic to parse the item's current name, splitting the base name and extension into separate form properties (if it is a file).
- [x] 2.4 Implement the `save` method to validate, invoke the form object's `updateItem()` method, dispatch a success event, and close the modal.

## Phase 3: Integration (UI Wiring)

- [x] 3.1 Create `resources/views/livewire/projects/files/rename-item-modal.blade.php`.
- [x] 3.2 Build the UI using Flux Pro UI components for the modal and form elements.
- [x] 3.3 Create the input field for the base name. For files, display the extension as a static, uneditable suffix block (e.g. using a Flux input group suffix).
- [x] 3.4 Wire the rename trigger in the file browser (or dropdown) to dispatch an event that opens the `RenameItemModal`.

## Phase 4: Testing

- [x] 4.1 Create `tests/Feature/Livewire/Projects/Files/RenameItemModalTest.php`.
- [x] 4.2 Test: Renaming a folder operates correctly without extensions.
- [x] 4.3 Test: Renaming a file splits the extension on mount and saves correctly while preserving the extension.
- [x] 4.4 Test: Uniqueness validation fails correctly if a file or folder with the target name already exists in the same directory.
- [x] 4.5 Test: Unauthorized user gets a 403 Forbidden when attempting to mount or submit the rename.
