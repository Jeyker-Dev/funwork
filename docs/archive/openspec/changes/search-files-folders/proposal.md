# Proposal: search-files-folders

## Intent

Implement a global search feature for files and folders to allow users to quickly locate items across the entire project hierarchy without manually navigating folders.

## Scope

### In Scope
- Global search for files and folders by name using Eloquent `LIKE`.
- Live search using Livewire `wire:model.live.debounce` bound to `$searchQuery` in the file index component.
- Display a breadcrumb/path subtitle on `folder-card` and `file-card` grids when a search is active (truncated with hover tooltip).
- Restore the original `currentFolderId` view state when the search is cleared or closed.

### Out of Scope
- Full-text search within file contents.
- Advanced filtering by date, size, or file type.
- Third-party search services like Algolia or Meilisearch.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `files-management`: Adding global search capability to the files index, modifying the card components to conditionally display path breadcrumbs when in search mode.

## Approach

Use Livewire's built-in state management by adding a `$searchQuery` property to the files index component. When `$searchQuery` has a value, ignore `currentFolderId` and query all folders and files globally using Eloquent `LIKE '%{query}%'` on the `name` column. Pass a flag to the `folder-card` and `file-card` components to show a subtle, truncated breadcrumb path with a tooltip. When the query is cleared, re-enable `currentFolderId` filtering to restore the original view.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `resources/views/livewire/pages/files/index.blade.php` | Modified | Add search input, pass search state to cards |
| `app/Livewire/Pages/Files/Index.php` | Modified | Add `$searchQuery`, modify Eloquent query for global search |
| `resources/views/components/folder-card.blade.php` | Modified | Add conditional breadcrumb/path subtitle with tooltip |
| `resources/views/components/file-card.blade.php` | Modified | Add conditional breadcrumb/path subtitle with tooltip |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Performance hit on large directories due to `LIKE` | Low/Med | Limit search results via pagination/max items limit |
| UI breakage on long breadcrumb paths | Low | Use text truncation (`truncate`) and hover tooltips |

## Rollback Plan

Revert the PR containing the Livewire component changes. Since there are no database schema changes, a code revert will instantly restore the previous state without data loss.

## Dependencies

- None.

## Success Criteria

- [ ] Typing in the search input globally filters files and folders by name.
- [ ] Search results display path breadcrumbs on their respective cards.
- [ ] Breadcrumbs are only visible during active search and use truncation/tooltips.
- [ ] Clearing the search returns the user to the exact folder they were initially viewing.
