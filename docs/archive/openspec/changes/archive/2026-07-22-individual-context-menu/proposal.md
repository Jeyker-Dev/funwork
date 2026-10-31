# Proposal: Individual Context Menu

## Intent

Introduce individual context menus to file and folder cards. This allows users to perform quick actions (Download, Move, Delete, Copy Link) on single items without needing to use the bulk selection interface, improving the speed and intuition of single-item operations.

## Scope

### In Scope
- Add `<flux:dropdown>` context menus to individual file and folder cards.
- Shift selection focus: selecting a single action will clear existing bulk selection and assign `$selectedIds = [$id]` to trigger existing modals natively.
- Async Folder ZIP Delivery: Background `CreateFolderZipJob` with Toast feedback and DatabaseNotification delivery.
- "Copy Link" action: copy the internal app route to the user's clipboard.

### Out of Scope
- Public links or external sharing permissions via the "Copy Link" action.
- Re-architecting existing move/delete modals (we will reuse the bulk modals).
- Sync folder downloads (all folder downloads will be async).

## Capabilities

### New Capabilities
- `folder-zip-export`: Handles async background zipping and database notifications for folder downloads.
- `item-context-menu`: Manages the individual item dropdown UI and selection state shifting.

### Modified Capabilities
- None

## Approach

We will inject `<flux:dropdown>` components into `file-card.blade.php` and `folder-card.blade.php`. Clicking a contextual action will dispatch an event or Livewire method that sets `$selectedIds = [$id]`, immediately overriding any bulk selection, and opens the corresponding existing modal (e.g., delete, move).
For folder downloads, the action dispatches `CreateFolderZipJob` and shows a Toast ('Preparing download...'). Upon completion, the job creates a `DatabaseNotification`. A Livewire polling component will display the download link to the user. "Copy Link" will use the Clipboard API to copy the internal item URL.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `resources/views/livewire/files/file-card.blade.php` | Modified | Add individual context dropdown. |
| `resources/views/livewire/files/folder-card.blade.php` | Modified | Add individual context dropdown. |
| `resources/views/livewire/files/index.blade.php` | Modified | Methods to handle single-item action triggers and focus shifts. |
| `app/Jobs/CreateFolderZipJob.php` | New | Background worker to zip folder contents. |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Large folder ZIP timeouts | Medium | Implement chunking or strict queue timeout limits; notify user on failure. |
| Notification polling overhead | Low | Use conservative polling intervals (e.g., `wire:poll.5s`) only when active. |

## Rollback Plan

Revert modifications to card views and `index.blade.php`. Delete `CreateFolderZipJob.php`.

## Dependencies

- Laravel Queue Worker must be running.
- `notifications` database table must exist and be migrated.

## Success Criteria

- [ ] Users can trigger actions on a single item via a dropdown menu.
- [ ] Triggering a menu action clears any previous multi-item selection.
- [ ] Folder downloads happen asynchronously, notify the user via DB, and provide a download link.
- [ ] Copy Link correctly saves the internal URL to the clipboard.
