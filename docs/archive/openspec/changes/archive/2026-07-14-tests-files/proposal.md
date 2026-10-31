# Proposal: tests-files

## Intent

Refactor the file management features to comply with Clean Architecture and provide comprehensive Pest test coverage. The current implementation couples database logic inside the Livewire component and uses hardcoded paths for zip file generation, making it difficult to test and maintain. This change will extract validation and persistence into Form Objects, utilize Laravel's `Storage` facade uniformly, and introduce extensive test coverage (including edge cases).

## Scope

### In Scope
- Refactor the files Livewire component (`resources/views/pages/projects/files/⚡index.blade.php`) to use Form Objects for file uploads and folder creation.
- Refactor `DownloadFilesZipAction` to exclusively use the Laravel `Storage` facade instead of hardcoded paths.
- Write Feature tests for the Livewire component covering navigation, folder creation, file uploads, and selections using Pest.
- Write Unit/Integration tests for `DownloadFilesZipAction` to ensure the correct ZIP generation and download response.
- Cover edge cases such as deleting files that are currently in use or being processed.

### Out of Scope
- Refactoring the frontend UI beyond what is necessary to accommodate Form Object integration.
- Adding new file management features like moving files between folders.

## Capabilities

### New Capabilities
- `files-management`: Refactored Clean Architecture implementation for handling file uploads, folder creation, zip downloads, and file deletion.

### Modified Capabilities
- None

## Approach

1. **Refactor Livewire Component:**
   - Extract the folder creation logic and file uploading logic from the `index.blade.php` Livewire component into dedicated Form Objects (e.g., `FolderForm`, `UploadFileForm`) following `laravel-clean-architecture`.
2. **Refactor Action:**
   - Update `DownloadFilesZipAction` to use `Storage::disk('local')` operations entirely, replacing any raw PHP `ZipArchive` calls that depend on absolute paths with Laravel storage mechanisms where possible, or managing temporary files strictly within the `Storage` facade.
3. **Test Implementation:**
   - Use `Storage::fake('local')` to isolate file operations.
   - Use `Livewire::test()` to assert state changes, form object validation, and correct dispatching of UI feedback.
   - Write dedicated test cases targeting edge conditions: e.g., attempting to delete a file that is in use or part of an active process, and handling invalid zip generation states.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Livewire/Forms/` | New | Form objects for folder and file management |
| `resources/views/pages/projects/files/⚡index.blade.php` | Modified | Delegate state and validation to Form Objects |
| `app/Actions/Files/DownloadFilesZipAction.php` | Modified | Replace hardcoded storage paths with Storage facade |
| `tests/Feature/Projects/FilesTest.php` | New | Pest tests for the Livewire component |
| `tests/Feature/Actions/DownloadFilesZipActionTest.php` | New | Pest tests for the ZIP generation action |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| ZipArchive dependency on real filesystem paths | Medium | We will utilize `Storage::path()` securely within the local disk to interface with ZipArchive, while keeping temp files isolated. |
| Test storage leaking to real storage | Low | Always use `Storage::fake('local')` in Pest's `beforeEach` block. |

## Rollback Plan

Revert the commits introducing the Form Objects and Action refactoring. The tests can remain or be reverted if the component signature reverts.

## Dependencies

- Pest v4
- Laravel Livewire v4

## Success Criteria

- [ ] `DownloadFilesZipAction` no longer contains direct `storage_path()` references, using `Storage` appropriately.
- [ ] Livewire component delegates folder creation and file uploads to Form Objects.
- [ ] Pest tests execute successfully covering the Livewire component and Action.
- [ ] Edge cases (like deleting files in use) are explicitly covered by tests.

## Proposal question round

*These questions aim to refine our understanding of the edge cases and business rules before moving to specs:*
1. **Edge Case Definition:** When you mention "deleting files that are currently in use", what constitutes "in use"? Are files linked to active tasks, or do we mean concurrent download/access?
2. **File Deletion Rules:** When a folder is deleted, should all nested files and subfolders be cascadingly deleted, or is folder deletion restricted if it contains items?
