# Design: tests-files

## Technical Approach

We will extract the folder creation and file upload logic from the `Files/Index` Livewire component into two dedicated Form Objects (`FolderForm` and `UploadFileForm`). We will update `DownloadFilesZipAction` to securely use Laravel's `Storage` facade, and we will create a new `DeleteSelectionAction` to safely handle file deletions, ensuring items that are "in use" cannot be deleted. Finally, we will implement comprehensive Pest tests for the Livewire component and both Actions using `Storage::fake()`.

## Architecture Decisions

### Decision: Extraction of Form Logic

**Choice**: Create `app/Livewire/Forms/FolderForm.php` and `app/Livewire/Forms/UploadFileForm.php`.
**Alternatives considered**: Moving validation into an Action class or keeping it inline within the component.
**Rationale**: Adheres to the `laravel-clean-architecture` pattern, which dictates that form validation and database persistence tied to Livewire components should be handled exclusively by Form Objects.

### Decision: Abstracting ZIP Paths

**Choice**: Use `Storage::disk('local')->path()` inside `DownloadFilesZipAction` for ZIP creation.
**Alternatives considered**: Using `storage_path()` directly or relying on an entirely in-memory ZIP package.
**Rationale**: PHP's native `ZipArchive` extension requires physical file paths. By strictly using the `Storage` facade to resolve absolute paths, the logic becomes fully testable with `Storage::fake('local')` during Pest testing, avoiding any side effects on the real filesystem.

### Decision: Safe File Deletion Handling

**Choice**: Introduce `app/Actions/Files/DeleteSelectionAction.php` to handle iterative deletion of files and folders. We will also define an `isInUse()` method on the `ProjectFile` model to satisfy the requirement of prohibiting deletion of active files.
**Alternatives considered**: Implementing deletion and safety checks directly within the Livewire component.
**Rationale**: Bulk recursive deletions and domain rule checks (e.g., verifying if a file is in use) are complex multi-step processes. Under Clean Architecture, these must reside in an Action class.

## Data Flow

    [ Livewire Component ]
         │         │
         │         └─ (Create/Upload) ─→ [ Form Object ] ──→ Database & Storage
         │
         └─ (Download/Delete) ─→ [ Action ] ──→ Storage (via Facade) & Response

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Livewire/Forms/FolderForm.php` | Create | Validates input and creates new folder records. |
| `app/Livewire/Forms/UploadFileForm.php` | Create | Validates files, stores them via Storage facade, and creates `ProjectFile` records. |
| `app/Actions/Files/DeleteSelectionAction.php` | Create | Iterates through selection to safely delete files and folders, enforcing "in use" constraints. |
| `resources/views/pages/projects/files/⚡index.blade.php` | Modify | Remove inline validation/creation logic; delegate to Form Objects; bind download and delete actions. |
| `app/Actions/Files/DownloadFilesZipAction.php` | Modify | Replace `storage_path()` with `Storage::disk('local')->path()` and ensure temporary folders are managed via `Storage`. |
| `app/Models/ProjectFile.php` | Modify | Add an `isInUse(): bool` method to encapsulate the deletion safety rule. |
| `tests/Feature/Projects/FilesTest.php` | Create | Pest tests covering Livewire component rendering and Form Object behaviors using `Livewire::test()` and `Storage::fake()`. |
| `tests/Feature/Actions/DownloadFilesZipActionTest.php` | Create | Pest tests for ZIP generation using fake storage. |
| `tests/Feature/Actions/DeleteSelectionActionTest.php` | Create | Pest tests verifying successful file deletions and asserting that "in use" files throw exceptions. |

## Interfaces / Contracts

```php
// In app/Models/ProjectFile.php
public function isInUse(): bool
{
    // Placeholder for future logic (e.g., checking active jobs or relations)
    return false;
}
```

```php
// In app/Actions/Files/DeleteSelectionAction.php
public function execute(Project $project, array $selectedIds): void
{
    // Iterates over $selectedIds. If a ProjectFile returns true for isInUse(),
    // throws ValidationException. Otherwise deletes the file from storage and DB.
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Integration | Folder Form | Use `Livewire::test()` to assert validation rules and folder creation in the DB. |
| Integration | Upload Form | Use `Livewire::test()` with `Storage::fake()` to assert successful uploads, DB records, and toast notifications. |
| Unit / Action | ZIP Download | Mock `ProjectFile`/`Folder`, populate `Storage::fake('local')`, execute `DownloadFilesZipAction`, and assert the ZIP file is created. |
| Unit / Action | Safe Deletion | Call `DeleteSelectionAction` with a mocked "in-use" file and assert that a `ValidationException` is thrown. |

## Migration / Rollout

No database migrations required. The system will deploy seamlessly as new Form Objects and Actions are fully isolated.

## Open Questions

- [ ] What specific database relations, locks, or job statuses will ultimately determine if a `ProjectFile` is "in use"? (Currently stubbed to return `false`).
- [ ] Should folder deletion automatically cascade and delete all children files, or should it fail if the folder is not empty? (Assuming cascade for the initial implementation).
