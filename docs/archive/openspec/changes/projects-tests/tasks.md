# Task Breakdown: projects-tests

Review Workload Forecast
Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

## Phase 1: Setup and Validation
- [x] Ensure Pest is properly configured for Volt testing (`Volt::test()`).
- [x] Verify that `UserFactory` and `ProjectFactory` are available and create standard records.

## Phase 2: Index Page Tests (`tests/Feature/Livewire/Pages/Projects/IndexTest.php`)
- [x] **Empty State Rendering**: Write a test verifying that when a user has no projects, the empty state text is shown and the `projects.create-project` component is rendered.
- [x] **Populated State Rendering**: Write a test verifying that when projects exist, the projects list view prompt is displayed along with the `projects.show-projects` component.
- [x] **Event Reactivity**: Write a test verifying that the component reacts to the `project-created` event by refreshing its projects data.

## Phase 3: Project Creation Tests (`tests/Feature/Livewire/Components/Projects/CreateProjectTest.php`)
- [x] **UI Context Rendering**: Write a test verifying the trigger button variations based on the `$elementName` property (header vs default empty state).
- [x] **Required Fields Validation**: Write a test verifying that attempting to save without `name`, `description`, `icon`, `type`, `status`, and `ownerId` fails validation and does not persist to the database.
- [x] **Date Format Validation**: Write a test verifying that invalid dates for `dueDate` and `archiveDate` trigger validation errors.
- [x] **Successful Project Creation**: Write a test verifying that providing valid inputs successfully creates a project in the database. Ensure it verifies the `archiveDate` -> `archived_at` mapping, checks for the Flux toast notification, verifies the modal closes, and confirms the `project-created` event is dispatched.

## Phase 4: Show Projects Tests (`tests/Feature/Livewire/Components/Projects/ShowProjectsTest.php`)
- [x] **Project List Rendering**: Write a test verifying that a populated list of projects displays correct information (name, description, owner, status, due date).
- [x] **Search Filtering**: Write a test verifying that updating the `$search` property correctly filters the visible projects list.
- [x] **Search Event Reactivity**: Write a test verifying that the component responds to the `search-updated` event, correctly updating the `$search` property and filtering the list.
- [x] **Creation Event Reactivity**: Write a test verifying that the component refreshes its project list data when the `project-created` event is dispatched.
