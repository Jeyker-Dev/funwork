# Delta for Files Management

## ADDED Requirements

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
