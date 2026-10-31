# Delta for files-management

## ADDED Requirements

### Requirement: Item Renaming

The system MUST allow users to rename files and folders using a dedicated modal, ensuring strict uniqueness validation and protecting file extensions from modification.

#### Scenario: Renaming a folder

- GIVEN an existing folder
- WHEN the user opens the rename modal
- THEN the system MUST auto-focus the input field with the folder name selected
- AND upon submission, the folder MUST be renamed

#### Scenario: Renaming a file with extension splitting

- GIVEN an existing file named "document.pdf"
- WHEN the user opens the rename modal
- THEN the input field MUST display "document" and be auto-focused
- AND the extension ".pdf" MUST be displayed as a static, uneditable suffix
- AND upon submission, the file MUST be renamed with the extension preserved

#### Scenario: Uniqueness validation against files and folders

- GIVEN a directory containing a file "report.pdf" and a folder "report"
- WHEN the user attempts to rename an item to "report" (if folder) or "report.pdf" (if file)
- THEN the system MUST reject the rename
- AND display a validation error indicating uniqueness collision

#### Scenario: Unauthorized rename attempt

- GIVEN a user without `update` permissions for an item
- WHEN the user attempts to rename the item
- THEN the system MUST deny the action based on existing policies
