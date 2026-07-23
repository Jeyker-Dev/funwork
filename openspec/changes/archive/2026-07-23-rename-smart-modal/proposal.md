# Proposal: Rename Smart Modal

## Intent

Improve the file and folder renaming experience in Project Phoenix by introducing a dedicated "Smart Modal" that safely handles extensions and validates uniqueness to prevent collisions.

## Scope

### In Scope
- Create a dedicated `RenameItemModal` Livewire component.
- Implement a `RenameItemForm` Form Object.
- Uniqueness validation against both files (`ProjectFile`) and folders (`Folder`) in the same directory.
- Split filename base and extension in the UI, keeping the extension as a static suffix block.
- Leverage existing `update` policies for permissions.

### Out of Scope
- Audit logging for rename actions.
- Renaming multiple items at once (batch renaming).
- Modifying file extensions directly through the modal.

## Capabilities

> This section is the CONTRACT between proposal and specs phases.
> The sdd-spec agent reads this to know exactly which spec files to create or update.

### New Capabilities
- None

### Modified Capabilities
- `files-management`: Add strict validation rules for item renaming (checking both files and folders) and split extension UI behavior for files.

## Approach

We will build a new Livewire component (`RenameItemModal`) combined with a Form Object (`RenameItemForm`) to isolate the rename logic, following clean architecture and Livewire best practices. The UI will parse the current filename, separating the base name from the extension. The input field will only allow editing the base name, with the extension displayed as a static, uneditable suffix block next to the input. Upon submission, the form object will validate the new name's uniqueness across both `ProjectFile` and `Folder` models in the current directory, relying on existing `update` policies to authorize the action.

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `app/Livewire/` | New | Add `RenameItemModal` component |
| `app/Livewire/Forms/` | New | Add `RenameItemForm` object |
| `resources/views/livewire/` | New | Add Blade view for the modal |
| `app/Policies/` | Unchanged | Rely on existing `update` policy |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Uniqueness collision between files and folders | Med | Custom validation rule in `RenameItemForm` that checks both `ProjectFile` and `Folder` tables. |
| Edge cases with missing extensions or hidden files (e.g. `.env`) | Low | Ensure extension parsing logic safely handles edge cases. |

## Rollback Plan

Revert the PR adding the `RenameItemModal` component and restore the previous rename UI implementation (if any existed before this change). Since there are no database schema migrations, rollback is purely code-based.

## Dependencies

- None

## Success Criteria

- [ ] Users can open a modal to rename a file or folder.
- [ ] For files, the extension is displayed as a static suffix and cannot be edited.
- [ ] Renaming to a name that matches an existing file or folder in the same directory fails with a validation error.
- [ ] Only authorized users (per `update` policy) can successfully rename an item.
