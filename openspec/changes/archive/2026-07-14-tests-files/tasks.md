# Tasks: tests-files

## Review Workload Forecast

| Field | Value |
|-------|-------|
| Estimated changed lines | ~600 lines |
| 400-line budget risk | High |
| Chained PRs recommended | Yes |
| Suggested split | PR 1 (Actions) → PR 2 (Forms) → PR 3 (Component) |
| Delivery strategy | ask-on-risk |
| Chain strategy | pending |

Decision needed before apply: Yes
Chained PRs recommended: Yes
Chain strategy: pending
400-line budget risk: High

### Suggested Work Units

| Unit | Goal | Likely PR | Notes |
|------|------|-----------|-------|
| 1 | Create and Update Actions | PR 1 | Base: feature/tracker branch. `DownloadFilesZipAction` & `DeleteSelectionAction` + tests. |
| 2 | Extract Form Objects | PR 2 | Base: PR 1 branch. `FolderForm` and `UploadFileForm` + tests. |
| 3 | Refactor Component and Modal | PR 3 | Base: PR 2 branch. Update `⚡index.blade.php`, add confirmation modal, Livewire tests. |

## Phase 1: Foundation (Actions)

- [x] 1.1 Update `app/Actions/Files/DownloadFilesZipAction.php` to use `Storage::disk('local')->path()` securely.
- [x] 1.2 Write `tests/Feature/Actions/DownloadFilesZipActionTest.php` using `Storage::fake('local')`.
- [x] 1.3 Create `app/Actions/Files/DeleteSelectionAction.php` to handle cascading deletion of files and folders (per user decision, ignoring "in use" logic).
- [x] 1.4 Write `tests/Feature/Actions/DeleteSelectionActionTest.php`.

## Phase 2: Form Objects

- [x] 2.1 Create `app/Livewire/Forms/FolderForm.php` and migrate folder creation validation and DB logic.
- [x] 2.2 Create `app/Livewire/Forms/UploadFileForm.php` and migrate file validation and upload DB logic.
- [x] 2.3 Write Pest tests for both Form Objects (`FolderFormTest` and `UploadFileFormTest`).

## Phase 3: Component Wiring & UI

- [x] 3.1 Refactor `resources/views/pages/projects/files/⚡index.blade.php` and its Livewire class to use `FolderForm` and `UploadFileForm`.
- [x] 3.2 Add a `flux:modal` named `delete-confirmation` to `⚡index.blade.php` to require confirmation before cascading deletion.
- [x] 3.3 Connect the "Eliminar" floating button to open the modal, and wire the modal's confirm button to trigger `DeleteSelectionAction`.
- [x] 3.4 Write `tests/Feature/Livewire/Projects/Files/IndexTest.php` covering folder creation, uploads, downloading, and the new deletion modal flow.
