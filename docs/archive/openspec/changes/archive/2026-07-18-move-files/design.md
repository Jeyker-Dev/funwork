# Design: Move Files

## Technical Approach

Implement a dedicated Livewire modal (`MoveItemsModal`) for selecting the destination directory, incorporating Flux Pro UI components for optimal UX. The modal allows navigating the folder structure and optionally creating new folders inline without triggering nested modals. Once a destination is selected, `MoveSelectionAction` will execute synchronous database updates on `folder_id` for files and `parent_id` for folders. It will validate constraints (preventing the "Inception Paradox") and auto-resolve name collisions by appending a counter to duplicates.

## Architecture Decisions

### Decision: Inline Folder Creation

**Choice**: Include an inline `<flux:input>` that toggles visibility within the move modal instead of a separate `<flux:modal>`.
**Alternatives considered**: Opening a separate `<flux:modal name="create-folder">` on top of the move modal.
**Rationale**: Nested modals are generally bad for UX and focus management, especially with Flux's focus trapping. An inline toggle keeps the user within the same context and avoids complex z-index/focus state issues.

### Decision: Auto-Renaming on Collision

**Choice**: Backend auto-renaming (appending `(1)`, `(2)`, etc.) upon collision in the target folder.
**Alternatives considered**: Prompting the user to rename or skipping the file.
**Rationale**: Prompting would require complex multi-step modal states. Auto-renaming provides a frictionless experience, similar to OS-level file moves.

### Decision: Synchronous Logical Move

**Choice**: Perform a synchronous database update within a transaction.
**Alternatives considered**: Dispatching a background job.
**Rationale**: Since files are stored flat on S3 and we only update the logical relationships (`folder_id`/`parent_id`), the operation is fast enough to run synchronously and provides immediate UI feedback.

## Data Flow

    MoveItemsModal ──(Target Folder & Items)──→ MoveSelectionAction
                                                       │
                                                       ├── Validate Inception Paradox
                                                       ├── Resolve Name Collisions
                                                       └── Update DB (Transaction) ──→ ProjectFile (folder_id)
                                                                                   ──→ Folder (parent_id)

## File Changes

| File | Action | Description |
|------|--------|-------------|
| `app/Livewire/Projects/Files/MoveItemsModal.php` | Create | Livewire component for navigating the folder tree and dispatching the move action. |
| `resources/views/livewire/projects/files/move-items-modal.blade.php` | Create | UI using Flux components (`<flux:modal>`, `<flux:breadcrumbs>`), inline folder creation, and focus trap. |
| `app/Actions/Files/MoveSelectionAction.php` | Create | Handles synchronous validation, auto-renaming logic, and DB updates for folders and files. |
| `resources/views/pages/projects/files/⚡index.blade.php` | Modify | Include the `<livewire:projects.files.move-items-modal>` component, trigger it via Contextual Action Bar or bulk actions, and listen to events. |

## Interfaces / Contracts

```php
// app/Actions/Files/MoveSelectionAction.php
namespace App\Actions\Files;

class MoveSelectionAction
{
    /**
     * @param Project $project The active project
     * @param array $selectedIds Format: ['file_1', 'folder_2']
     * @param int|null $targetFolderId Destination folder ID (null for root)
     * @throws ValidationException
     */
    public function execute(Project $project, array $selectedIds, ?int $targetFolderId): void;
}
```

## Testing Strategy

| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit | `MoveSelectionAction` | Test inception paradox validation, auto-renaming on collisions, and root vs subfolder moving. |
| Integration | `MoveItemsModal` | Test folder navigation, inline folder creation logic, and dispatching the action. |
| Feature | Files UI (`⚡index.blade.php`) | Test clicking "Move" populates the modal and correctly refreshes upon completion. |

## Migration / Rollout

No data migration required. Feature relies entirely on existing `folder_id` and `parent_id` columns.

## Open Questions

- [ ] Will moving a folder containing thousands of files trigger PHP execution limits despite only updating the parent folder? (Since we only update the parent folder's `parent_id`, it is O(1) DB operations per folder moved, so it should be fine).
