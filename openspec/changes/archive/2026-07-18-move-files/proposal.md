# Proposal: Move files

## Intent

Enable users to reorganize their workspace by moving files and folders into different directories within a project. This reduces clutter and improves project structure management.

## Scope

### In Scope
- Single and bulk moving of files and folders via a dedicated UI modal.
- Breadcrumb navigation within the modal to select the target destination.
- Inline creation of new folders within the move modal.
- Auto-renaming of files/folders on destination collision (e.g., appending `(1)`).
- Synchronous logical movement (updating `folder_id` / `parent_id` only).
- Strict backend validations (preventing Inception Paradox, same-directory moves).

### Out of Scope
- Physical movement of files in S3 (files remain flat in S3).
- Background jobs for moving (logical move is fast enough for synchronous execution).
- Cross-project file movement.

## Capabilities

### New Capabilities

- None

### Modified Capabilities

- `files-management`: Adding rules and constraints for moving files and folders, handling duplicates, and validating directory structures.

## Approach

Implement a new `MoveItemsModal` Livewire component that displays only folders and allows breadcrumb navigation for target selection. An inline text input will handle on-the-fly folder creation to avoid nested modals. The movement logic will be encapsulated in a `MoveSelectionAction` which performs synchronous database updates (`folder_id`/`parent_id`) since files are stored flat on S3. Strict backend validation will enforce constraints like the Inception Paradox.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Livewire/MoveItemsModal.php` | New | Livewire modal for target selection |
| `resources/views/livewire/move-items-modal.blade.php` | New | UI for the modal, folder list, and inline creation |
| `app/Actions/MoveSelectionAction.php` | New | Encapsulates the logical move and conflict resolution |
| `app/Livewire/FilesManagement.php` | Modified | Integration with the new modal and move action |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Inception Paradox (moving a folder into itself) | High | Strict validation in `MoveSelectionAction` before updating DB records. |
| Name collisions | Medium | Auto-rename strategy appending `(1)`, `(2)` for duplicate names. |

## Rollback Plan

Revert the commits introducing `MoveItemsModal` and `MoveSelectionAction`. If data corruption occurs during a move operation (e.g., orphaned records due to a bug), manual DB cleanup of `parent_id`/`folder_id` relationships may be required.

## Dependencies

- None

## Success Criteria

- [ ] Users can successfully move one or more files/folders to a new target directory.
- [ ] Attempting to move a folder into itself or its children fails gracefully with a clear error.
- [ ] Name conflicts are automatically resolved by appending a counter.
- [ ] Users can create a new folder inline from the modal without opening another modal.
