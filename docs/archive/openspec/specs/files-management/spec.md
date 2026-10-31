# Files Management Specification

## Purpose

Defines the system behaviors for managing files and folders within projects, focusing on robust file operations, ZIP archive generation, and strict file lifecycle validation.

## Requirements

### Requirement: Folder Management

The system MUST allow users to create folders with strict validation constraints.

#### Scenario: Valid folder creation

- GIVEN a valid folder name
- WHEN the folder creation is requested
- THEN the system MUST create the folder
- AND present a success confirmation

#### Scenario: Invalid folder creation

- GIVEN an invalid or empty folder name
- WHEN the folder creation is requested
- THEN the system MUST reject the creation
- AND return validation errors

### Requirement: File Uploads

The system MUST allow users to upload files and persist them correctly to the underlying storage.

#### Scenario: Successful file upload

- GIVEN a valid file selection
- WHEN the file upload is submitted
- THEN the system MUST persist the file in the designated storage
- AND confirm the successful upload

### Requirement: ZIP Archive Generation

The system MUST generate ZIP archives for selected files securely using abstract storage definitions without relying on hardcoded system paths.

#### Scenario: Valid ZIP generation

- GIVEN a selection of existing files
- WHEN the user requests to download them as a ZIP
- THEN the system MUST generate a ZIP archive containing the files
- AND provide the archive for download

#### Scenario: Missing files in ZIP request

- GIVEN a request to download files
- WHEN one or more of the requested files do not exist in storage
- THEN the system MUST safely abort the archive generation
- AND return an error indicating the files are unavailable

### Requirement: File Deletion Safety

The system MUST allow file deletion but SHALL prohibit deleting files that are currently in use or actively being processed.

#### Scenario: Deleting an available file

- GIVEN a file that is not currently in use
- WHEN the user requests deletion
- THEN the system MUST permanently remove the file from storage
- AND confirm the deletion

#### Scenario: Deleting an in-use file

- GIVEN a file that is locked, in use, or part of an active process
- WHEN the user requests deletion
- THEN the system MUST NOT delete the file
- AND MUST surface an error explaining the file is in use

### Requirement: Move Items

The system MUST allow users to logically move files and folders to different directories, resolving name collisions automatically and preventing circular dependencies.

#### Scenario: Opening modal and navigating folders

- GIVEN a selection of files and folders
- WHEN the user opens the move modal
- THEN the system MUST display the available folder structure
- AND the user MUST be able to navigate to a target destination using breadcrumbs

#### Scenario: Creating a new folder inline

- GIVEN the move modal is open
- WHEN the user inputs a new folder name and requests inline creation
- THEN the new folder MUST be created immediately in the current path
- AND become available as a valid destination without reopening the modal

#### Scenario: Validation preventing move to the same directory

- GIVEN a selection of items in a specific directory
- WHEN the user attempts to move them to the same directory
- THEN the system MUST reject the move operation
- AND display an error indicating the source and target directories are identical

#### Scenario: Validation for Inception Paradox

- GIVEN a selected folder
- WHEN the user attempts to move it into itself or any of its descendent folders
- THEN the system MUST prevent the move
- AND present an error preventing the circular reference

#### Scenario: Auto-rename on duplicate files

- GIVEN a target folder containing an item named "document.txt"
- WHEN the user moves another item named "document.txt" into that target folder
- THEN the system MUST automatically rename the moved item to "document.txt (1)" (or the next available number)
- AND complete the move successfully

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
