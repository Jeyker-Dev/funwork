# folder-zip-export Specification

## Purpose

Handles asynchronous background zipping and database notifications for folder downloads to prevent timeouts and blocking on large exports.

## Requirements

### Requirement: Async Folder Zipping

The system MUST process folder downloads asynchronously using a background job, provide immediate UI feedback via a toast, and deliver the final ZIP file link through a database notification.

#### Scenario: Initiating a folder download

- GIVEN a user triggers a "Download" action on a folder
- WHEN the action is dispatched
- THEN a `CreateFolderZipJob` MUST be queued in the background
- AND the UI MUST display a toast indicating "Preparing download..."

#### Scenario: Successful folder zipping

- GIVEN a queued `CreateFolderZipJob` is processing
- WHEN the background job successfully creates the ZIP file
- THEN a `DatabaseNotification` MUST be created for the user containing the download link
- AND the active UI MUST poll for the notification and present the download link to the user

#### Scenario: Failed folder zipping

- GIVEN a queued `CreateFolderZipJob` is processing
- WHEN the background job fails due to timeout or error
- THEN a `DatabaseNotification` SHOULD be created for the user indicating the failure
