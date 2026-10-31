## Exploration: Move files

### Current State
- The File Manager uses a Volt component (`resources/views/pages/projects/files/⚡index.blade.php`) that holds `selectedIds` (format: `['file_1', 'folder_2']`) and `currentFolderId`.
- The Floating Action Bar (`floating-bar.blade.php`) contains a "Mover" button, currently stubbed with `wire:click="moveSelected"`.
- Modals are managed as standalone Livewire components inside `app/Livewire/Projects/Files/`.
- The `Folder` model uses `parent_id` for hierarchy and provides an efficient `breadcrumbs` attribute by recursively iterating parents up to the root.

### Affected Areas
- `resources/views/pages/projects/files/⚡index.blade.php` — Needs to include the new modal and trigger it (e.g., via Flux events or `moveSelected` method).
- `resources/views/components/projects/files/floating-bar.blade.php` — Update the trigger for the Mover button.
- `app/Livewire/Projects/Files/MoveItemsModal.php` — New Livewire component to manage the destination folder selection state and navigation logic.
- `resources/views/livewire/projects/files/move-items-modal.blade.php` — The UI for the modal, functioning as a mini-explorer (only showing folders).
- `app/Actions/Files/MoveSelectionAction.php` — New dedicated Action class to execute the database move and strictly enforce validation rules (Same Directory & Inception Paradox).

### Approaches
1. **Standalone Livewire Modal (Recommended)**
   - Create `MoveItemsModal` that tracks its own `$targetFolderId` (independent of the main view's directory).
   - Display a mini-explorer showing folders. Fetch folders using `$targetFolderId`.
   - Incorporate a subtle "+ New Folder" button. It can trigger an inline creation input or call out to the existing `CreateFolderModal` (handling nested modal constraints via Flux).
   - Frontend validation: Prevent navigating into or selecting an invalid target by checking if the target's `id` or any of its ancestors' `id`s (via breadcrumbs) exist in the `selectedIds` array.
   - Backend validation: The `MoveSelectionAction` strictly aborts if moving to the original directory or into a descendant of the moved folder.
   - Pros: Clean separation of concerns. Modal state won't pollute the main file manager.
   - Cons: Slight duplication of folder listing logic inside the modal.
   - Effort: Medium

2. **Inline Navigation (Move Mode)**
   - Re-use the main view to navigate and select the destination.
   - Pros: Re-uses the existing main view UI perfectly.
   - Cons: Confusing UX. The user explicitly requested a "Modal Destination Selector" for the target selection.
   - Effort: High (changing existing Volt component flow).

### Recommendation
Proceed with **Approach 1**. Implement a standalone `MoveItemsModal` component and a dedicated `MoveSelectionAction`. To solve the "Inception Paradox" elegantly on the frontend, the modal can use the target folder's `breadcrumbs` to cross-reference against the currently selected `folder_X` IDs. If any breadcrumb ID matches a selected folder, the target is invalid. The backend must enforce this strictly as a final line of defense.

### Risks
- **Inception Paradox:** If not validated strictly on the backend, moving a folder into its own child will create a detached circular reference in the database, breaking the file manager tree.
- **Nested Modals:** Opening a "+ New Folder" modal over the "Move Items" modal may conflict with Focus Traps or z-index in `Flux UI`. If nested modals are problematic, the "+ New Folder" button could show a simple inline text input to create the folder without launching a second modal.
- **Same Directory Move:** Need to ensure the UI clearly disables the "Move here" button when the `targetFolderId` equals the original `currentFolderId`.

### Ready for Proposal
Yes. The architecture is clear and the orchestrator can proceed with proposing the solution.
