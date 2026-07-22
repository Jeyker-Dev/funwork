# item-context-menu Specification

## Purpose

Manages the individual item dropdown UI and selection state shifting, allowing users to perform quick actions on single items without using the bulk selection interface.

## Requirements

### Requirement: Individual Context Menu Rendering

The system MUST render a context menu on individual file and folder cards containing actions such as Download, Move, Delete, and Copy Link.

#### Scenario: Viewing a file or folder card

- GIVEN a user is viewing the file or folder grid
- WHEN they interact with an individual file or folder card
- THEN a `<flux:dropdown>` context menu MUST be available for that item

### Requirement: Focus Shift Selection Override

The system MUST override any existing bulk selection when an individual item action is triggered, shifting focus to only the item that initiated the action.

#### Scenario: Triggering an action with prior selection

- GIVEN the user has existing items selected (e.g., in a bulk selection state)
- WHEN the user triggers an action (like Move or Delete) from an individual item's context menu
- THEN the bulk selection state MUST be cleared
- AND the selection focus (`$selectedIds`) MUST be strictly set to the single item's ID `[$id]`
- AND the corresponding modal MUST be opened natively

### Requirement: Copy Link Action

The system MUST provide a "Copy Link" action that copies the internal URL of the item to the clipboard.

#### Scenario: Copying an internal link

- GIVEN a user opens the context menu on an item
- WHEN they select the "Copy Link" action
- THEN the internal application route for that item MUST be copied to the user's clipboard
- AND a success toast SHOULD be shown
