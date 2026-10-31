# Projects View Test Specifications

## Overview
This document outlines the test specifications for the Projects feature, covering the index page, project creation, and project list components.

## Testing Standards
- Tests MUST use Pest PHP combined with the Livewire/Volt testing helpers (e.g., `Volt::test()`).
- Tests MUST use the `RefreshDatabase` trait for database state isolation.
- Tests MUST authenticate a user (e.g., via `actingAs()`) before interacting with the components, as they depend on `auth()->user()`.

## 1. Projects Index Page Specifications
**Target Component:** `pages.projects.index`

### Scenarios
#### Scenario 1.1: Empty State Rendering
- **Given** no projects exist in the database for the authenticated user
- **When** the user visits the projects index page
- **Then** the page MUST display the empty state text
- **And** the page MUST render the `projects.create-project` component

#### Scenario 1.2: Populated State Rendering
- **Given** one or more projects exist in the database
- **When** the user visits the projects index page
- **Then** the page MUST display the projects list prompt/view
- **And** the page MUST render the `projects.show-projects` component

#### Scenario 1.3: Reactivity to Project Creation
- **Given** the user is on the projects index page
- **When** a `project-created` event is dispatched by a child component
- **Then** the page MUST refresh its projects data

## 2. Project Creation Modal Specifications
**Target Component:** `components.projects.create-project`
**Form Object:** `App\Livewire\Forms\ProjectForm`

### Scenarios
#### Scenario 2.1: UI Trigger Rendering Context
- **Given** the `create-project` component is rendered
- **When** the `$elementName` property is set to 'header'
- **Then** the component MUST render the header-specific trigger button
- **When** the `$elementName` property is NOT set (or empty)
- **Then** the component MUST render the default empty state card trigger button

#### Scenario 2.2: Required Fields Validation
- **Given** the user opens the project creation modal
- **When** the user attempts to submit the form with missing required fields (`name`, `description`, `icon`, `type`, `status`, `ownerId`)
- **Then** the component MUST return validation errors for all missing fields
- **And** the project MUST NOT be saved to the database

#### Scenario 2.3: Date Formatting Validation
- **Given** the user opens the project creation modal
- **When** the user submits invalid date formats for `dueDate` and `archiveDate`
- **Then** the component MUST return validation errors for the date fields
- **And** the project MUST NOT be saved to the database

#### Scenario 2.4: Successful Project Creation
- **Given** the user opens the project creation modal
- **When** the user submits valid data including all required fields and correctly formatted dates
- **Then** the project data MUST be successfully persisted to the database
- **And** the `archiveDate` MUST be correctly mapped to the `archived_at` database column (fixing the known `archive_at` typo)
- **And** a Flux toast notification MUST be dispatched to indicate success
- **And** the modal MUST be closed
- **And** the `project-created` event MUST be dispatched

## 3. Projects List Component Specifications
**Target Component:** `components.projects.show-projects`

### Scenarios
#### Scenario 3.1: Rendering the Project List
- **Given** multiple projects exist in the database
- **When** the `show-projects` component is rendered
- **Then** it MUST display the list of projects
- **And** it MUST include the project name, description, owner, status, and due date for each displayed project

#### Scenario 3.2: Search Filtering
- **Given** multiple projects exist with different names/descriptions
- **When** the user updates the `$search` property string
- **Then** the component MUST filter and display ONLY the projects matching the search string

#### Scenario 3.3: Reactivity to Search Event
- **Given** the `show-projects` component is rendered
- **When** a `search-updated` event is received with a query string
- **Then** the component MUST correctly update its `$search` property
- **And** the displayed list MUST reflect the new search filter

#### Scenario 3.4: Reactivity to Project Creation
- **Given** the `show-projects` component is rendered
- **When** a `project-created` event is dispatched
- **Then** the component MUST refresh its project data and display the updated list containing the new project
