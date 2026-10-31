# Projects View - Test Exploration

## Overview
The 'Projects' feature is built using Folio/Volt components. The main view is located at `resources/views/pages/projects/⚡index.blade.php`, which conditionally renders a project creation prompt (if there are no projects) or a list of projects (`show-projects`). The creation logic is handled by the `create-project` component using a Livewire Form object (`App\Livewire\Forms\ProjectForm`).

## Files Analyzed
- `resources/views/pages/projects/⚡index.blade.php`
- `resources/views/components/projects/⚡create-project.blade.php`
- `resources/views/components/projects/⚡show-projects.blade.php`
- `app/Livewire/Forms/ProjectForm.php`
- `app/Models/Project.php`
- `database/migrations/2026_03_30_010539_create_projects_table.php`

## User Flows to Test

### 1. Projects Index Page (`⚡index.blade.php`)
- **Empty State:** When no projects exist, the page should display the empty state text and render the `projects.create-project` component.
- **Populated State:** When projects exist, the page should display the list prompt and render the `projects.show-projects` component.
- **Event Listening:** The index component should correctly refresh its `with()` projects data when the `project-created` event is dispatched.

### 2. Project Creation Modal (`⚡create-project.blade.php` & `ProjectForm.php`)
- **Rendering:** The trigger button should render correctly depending on the `$elementName` (e.g. 'header' vs default empty state card).
- **Validation:** 
  - Required fields (`name`, `description`, `icon`, `type`, `status`, `ownerId`).
  - Date formatting validation (`dueDate` and `archiveDate`).
- **Submission:**
  - Creating a valid project persists the data to the database via `ProjectForm`.
  - On successful creation, a Flux toast is displayed and the modal is closed.
  - The `project-created` event is dispatched.
- **Bug Identified:** The `ProjectForm.php` currently has a typo when persisting data: `'archive_at' => $this->archiveDate` should be `'archived_at' => $this->archiveDate` to match the model's fillable properties and migration. This should be fixed and covered by a test.

### 3. Projects List Component (`⚡show-projects.blade.php`)
- **Rendering:** It should render the list of projects in the database, including their name, description, owner, status, and due date.
- **Search Filtering:** It should properly filter the projects when the `$search` string changes.
- **Event Syncing:** It should properly update the `$search` property when listening for the `search-updated` event.
- **Event Listening:** It should refresh its project data when the `project-created` event is dispatched.

## Recommended Testing Strategy
We should use Pest PHP combined with Livewire/Volt test helpers. 
Since these are Volt components, tests can be written leveraging the `Volt::test()` helper:
- **`ProjectsIndexTest.php`**: Test the rendering of `pages.projects.index` for both empty and populated states.
- **`CreateProjectTest.php`**: Test `components.projects.create-project` for validation rules, database persistence, and event dispatching. 
- **`ShowProjectsTest.php`**: Test `components.projects.show-projects` for rendering the list, searching, and event listening.

## Prerequisites for Tests
- `RefreshDatabase` trait to ensure database state isolation.
- `actingAs(User::factory()->create())` to ensure the owner is authenticated, as the components heavily depend on `auth()->user()`.
