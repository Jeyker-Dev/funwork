## Exploration: rename-smart-modal

### Current State
The Files module displays files and folders via `file-card.blade.php` and `folder-card.blade.php`, managed by the `⚡index.blade.php` Livewire component. Both cards feature a context menu (`flux:dropdown`) with Download, Copy Link, Move, and Delete actions. Currently, there is no UI or backend logic to rename an existing file or folder. Folders are created via `CreateFolderModal` using `FolderForm`, and files are uploaded via `UploadFileModal` using `UploadFileForm`. These existing forms do not validate uniqueness against both files and folders in the same directory, meaning duplicates could theoretically exist.

### Affected Areas
- `resources/views/pages/projects/files/⚡index.blade.php` — Needs to include the new Rename modal and listen to its success event to refresh the UI.
- `resources/views/components/projects/files/file-card.blade.php` — Needs a new "Rename" menu item that dispatches the `open-rename-modal` event with the file's ID and name.
- `resources/views/components/projects/files/folder-card.blade.php` — Needs a new "Rename" menu item that dispatches the `open-rename-modal` event with the folder's ID and name.
- `app/Livewire/Projects/Files/` — New component class for the modal (e.g., `RenameItemModal.php`).
- `resources/views/livewire/projects/files/` — New blade view for the modal (`rename-item-modal.blade.php`).
- `app/Livewire/Forms/` (Optional) — New Form Object (`RenameItemForm.php`) if validation and persistence are extracted from the component.

### Approaches
1. **Dedicated Component + Form Object (Clean Architecture Strict)**
   - Create `RenameItemModal` (Livewire Component) and `RenameItemForm` (Form Object).
   - *Pros*: Follows the strict `laravel-clean-architecture` guidelines by keeping validation and storage in `Livewire/Forms`.
   - *Cons*: Validation requires polymorphic context (knowing if it's a file or folder and querying both `ProjectFile` and `Folder` models for uniqueness). This makes the form object slightly complex.
   - *Effort*: Medium

2. **Dedicated Component Only (Consistent with existing modals)**
   - Create `RenameItemModal` with the validation and save logic directly in its `save()` method.
   - *Pros*: Faster to implement. Matches the exact pattern of `MoveItemsModal` which also interacts with both files and folders without a dedicated form object.
   - *Cons*: Slightly violates the strict Form Object rule, but pragmatic for polymorphic multi-model validation.
   - *Effort*: Low

### Recommendation
Proceed with **Approach 1** (Dedicated Component + Form Object). Even though it's polymorphic, encapsulating the complex uniqueness rule (checking both `ProjectFile` and `Folder` tables for name collisions in the same directory) inside a `RenameItemForm` ensures the Livewire component remains thin and strictly handles UI state (splitting the extension, managing modal visibility). For the UI, use Alpine.js `$refs` combined with Livewire's `#[On]` to pre-select the text and apply focus when the modal opens, while providing a seamless suffix for the file extension.

### Risks
- **Uniqueness Check**: The database might currently have non-unique names since the current upload/create logic doesn't strictly enforce DB-level cross-model uniqueness. Renaming might expose this if not handled properly.
- **Mime Type/Extension Mismatch**: Relying on string-splitting for extensions (`pathinfo`) might be tricky if a file has no extension or multiple dots (e.g., `archive.tar.gz`). We should split only the last segment, which `pathinfo` does, but we must ensure it behaves safely.

### Ready for Proposal
Yes. The orchestrator can proceed to the `sdd-propose` phase.
