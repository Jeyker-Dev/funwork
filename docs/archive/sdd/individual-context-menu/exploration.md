## Exploration: Individual Context Menu (Dropdown) in Files module

### Current State
The Files module displays files and folders using `file-card.blade.php` and `folder-card.blade.php`. These cards currently have a simple ellipsis `<button>` at the top right that is not hooked up to a menu. Bulk actions (Move, Delete, Download) are managed by `⚡index.blade.php`, which tracks selection via a `$selectedIds` array and triggers modals or synchronous actions when bulk operations are requested. Currently, the `DownloadFilesZipAction` processes both files and folders synchronously, which could result in timeouts for large folder downloads.

### Affected Areas
- `resources/views/components/projects/files/file-card.blade.php` — Replace the existing ellipsis `<button>` with `<flux:dropdown>` and `<flux:menu>` containing the single-file actions.
- `resources/views/components/projects/files/folder-card.blade.php` — Replace the existing ellipsis `<button>` with `<flux:dropdown>` and `<flux:menu>` containing the single-folder actions.
- `resources/views/pages/projects/files/⚡index.blade.php` — Needs new methods (`moveSingle`, `deleteSingle`, `downloadSingleFile`, `downloadFolderZip`) to handle single-item actions. It will overwrite `$this->selectedIds` with a one-element array containing the target ID and trigger the existing modals.
- `app/Jobs/CreateFolderZipJob.php` (New) — An asynchronous job to handle the zip creation of folders in the background to prevent request timeouts.

### Approaches
1. **Re-use Bulk State (Recommended)** — In the new methods on `⚡index.blade.php` (e.g. `moveSingle(string $id)`), assign `$this->selectedIds = [$id]` and immediately dispatch the modal events or show the modal.
   - Pros: Re-uses the existing modals (`DeleteConfirmationModal` and `MoveItemsModal`) exactly as they are without duplicating state variables. The counts for the delete modal will automatically update because they are `#[Computed]` properties based on `$selectedIds`.
   - Cons: Overwrites any existing bulk selection the user might have had if they click an individual action.
   - Effort: Low

2. **Separate State for Individual Actions** — Create new variables like `$singleTargetId` in `⚡index.blade.php` and update the modals to accept either bulk IDs or a single ID.
   - Pros: Preserves the user's bulk selection if they perform a single action.
   - Cons: Requires refactoring `DeleteConfirmationModal` and `MoveItemsModal` to support two different modes (bulk vs single).
   - Effort: Medium

### Recommendation
**Approach 1 (Re-use Bulk State)**. It perfectly satisfies the requirement of "assigning the file's ID to the Livewire state (e.g. `$selectedItems`) and triggering the same modal". For the folder download, dispatching a new `CreateFolderZipJob` is required because S3 streaming and zip creation for folders with multiple files can easily exceed normal HTTP timeouts.

### Risks
- Overwriting `$selectedIds` on individual action will clear the user's multi-selection. (Acceptable based on the requirements).
- Asynchronous Folder Download needs a way to deliver the result to the user (e.g., broadcasting an event, creating a database notification, or temporarily storing a link). The scope of notification must be decided during implementation.
- `flux:dropdown` has absolute positioning requirements. Placing it inside a card with `overflow-hidden` might clip the menu. We might need to ensure the card doesn't clip dropdown portals if `livewire-flux` renders them inline instead of teleporting.

### Ready for Proposal
Yes — the approach is validated. The orchestrator can proceed to the next phase, but should verify how the user expects to receive the ZIP file once the asynchronous `CreateFolderZipJob` finishes (e.g., database notification with download link, or Livewire broadcast event).
