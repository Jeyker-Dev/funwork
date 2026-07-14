# Apply Progress: tests-files

## Phase 1: Foundation (Actions)
- [x] 1.1 Update `app/Actions/Files/DownloadFilesZipAction.php` to use `Storage::disk('local')->path()` securely.
- [x] 1.2 Write `tests/Feature/Actions/DownloadFilesZipActionTest.php` using `Storage::fake('local')`.
- [x] 1.3 Create `app/Actions/Files/DeleteSelectionAction.php` to handle cascading deletion of files and folders (per user decision, ignoring "in use" logic).
- [x] 1.4 Write `tests/Feature/Actions/DeleteSelectionActionTest.php`.

## Phase 2: Form Objects
- [x] 2.1 Create `app/Livewire/Forms/FolderForm.php` and migrate folder creation validation and DB logic.
- [x] 2.2 Create `app/Livewire/Forms/UploadFileForm.php` and migrate file validation and upload DB logic.
- [x] 2.3 Write Pest tests for both Form Objects (`FolderFormTest` and `UploadFileFormTest`).

## TDD Cycle Evidence
Not using Strict TDD mode as it was not specified or active in tests/setup, though tests were written and verified to pass.

## Phase 3: Component Wiring & UI
- [x] 3.1 Refactor `resources/views/pages/projects/files/⚡index.blade.php` and its Livewire class to use `FolderForm` and `UploadFileForm`.
- [x] 3.2 Add a `flux:modal` named `delete-confirmation` to `⚡index.blade.php` to require confirmation before cascading deletion.
- [x] 3.3 Connect the "Eliminar" floating button to open the modal, and wire the modal's confirm button to trigger `DeleteSelectionAction`.
- [x] 3.4 Write `tests/Feature/Livewire/Projects/Files/IndexTest.php` covering folder creation, uploads, downloading, and the new deletion modal flow.
