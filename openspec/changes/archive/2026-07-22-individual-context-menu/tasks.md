Created At: 2026-07-22T18:47:33Z
Completed At: 2026-07-22T18:47:33Z
File Path: `file:///home/jeyker-dev/Work/funwork/openspec/changes/individual-context-menu/tasks.md`

# Tasks: individual-context-menu

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | 150-250 |
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
| 1 | Individual context menus and async folder download | PR 1 | main; tests included |

## Phase 1: Foundation / Infrastructure

- [x] 1.1 `app/Jobs/CreateFolderZipJob.php`: Create job (RED/GREEN/REFACTOR in Pest). Handle zipping, `DatabaseNotification` delivery, and failure paths.

## Phase 2: Core Implementation

- [x] 2.1 `resources/views/livewire/files/file-card.blade.php`: Add `<flux:dropdown>` for item context menu (Download, Move, Delete, Copy Link).
- [x] 2.2 `resources/views/livewire/files/folder-card.blade.php`: Add `<flux:dropdown>` for item context menu (Download, Move, Delete, Copy Link).
- [x] 2.3 `resources/views/livewire/files/index.blade.php`: Add/update Livewire methods to handle single-item action triggers, immediately overriding bulk selection (`$selectedIds = [$id]`) and opening existing modals.
- [x] 2.4 "Copy Link": Implement clipboard copy for the internal item URL, showing a success toast.

## Phase 3: Testing / Verification

- [x] 3.1 `tests/Feature/Livewire/FilesIndexTest.php`: Test context menu triggers clear existing selection and correctly set `$selectedIds = [$id]`.
- [x] 3.2 `tests/Feature/Jobs/CreateFolderZipJobTest.php`: Test background execution and DB notification delivery on success and failure.

## Phase 4: Cleanup

- [x] 4.1 Polish toast feedback ("Preparing download...") and ensure polling interval (`wire:poll.5s`) activates only when needed.
