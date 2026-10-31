# Tasks: move-files

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~650 |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Backend Action) → PR 2 (Livewire Modal & UI) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | `MoveSelectionAction` backend logic & tests | PR 1 | Base branch: main. Includes circular ref & collision tests. |
| 2 | `MoveItemsModal` & UI integration | PR 2 | Base branch: PR 1 branch. Includes modal & inline folder creation. |

## Phase 1: Core Implementation (Backend)

- [x] 1.1 Create `app/Actions/Files/MoveSelectionAction.php` to handle DB updates, auto-renaming collisions, and preventing circular dependencies.
- [x] 1.2 Write unit tests for `MoveSelectionAction` covering inception paradox validation, auto-renaming, and target directory validation.

## Phase 2: User Interface & Component

- [x] 2.1 Create `app/Livewire/Projects/Files/MoveItemsModal.php` Livewire component for folder navigation and inline folder creation.
- [x] 2.2 Create `resources/views/livewire/projects/files/move-items-modal.blade.php` using `<flux:modal>` and inline `<flux:input>` for creation.
- [x] 2.3 Write integration tests for `MoveItemsModal` verifying navigation, folder creation, and action dispatch.

## Phase 3: Wiring & Feature Tests

- [x] 3.1 Modify `resources/views/pages/projects/files/⚡index.blade.php` to include the modal component and trigger it from the UI.
- [x] 3.2 Write feature tests verifying the UI successfully triggers the modal, moves files, and updates the view upon completion.
