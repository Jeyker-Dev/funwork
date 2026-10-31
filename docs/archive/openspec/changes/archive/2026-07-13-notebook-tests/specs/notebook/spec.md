# Notebook Specification

## Purpose

Defines the behavioral specifications for the Notebook module, covering Model relations, Form object validation, and Livewire UI component states.

## Requirements

### Requirement: Notebook Model Integrity

The system MUST enforce correct relationships and fillable attributes for the Notebook model.

#### Scenario: Model initialization
- GIVEN a new Notebook instance
- WHEN instantiated with fillable attributes
- THEN it maps correctly to the project and creator relationships

### Requirement: Notebook Form Validation

The system MUST validate required fields and enforce invariants during Notebook creation and updates via the `NotebookForm`.

#### Scenario: Missing project ID
- GIVEN the NotebookForm
- WHEN submitting without a `project_id`
- THEN the system returns a validation error for the `project_id` field

#### Scenario: Missing creator
- GIVEN the NotebookForm
- WHEN submitting without a `created_by` field
- THEN the system returns a validation error for the `created_by` field

#### Scenario: Successful creation
- GIVEN valid form data including title, `project_id`, and `created_by`
- WHEN the `store` method is called
- THEN the notebook is persisted
- AND a success toast is dispatched
- AND the modal is closed
- AND the form is reset

### Requirement: Notebook UI States

The system MUST correctly render Notebook states in the Livewire components and dispatch appropriate events for interactivity.

#### Scenario: Empty state rendering
- GIVEN a project with no notebooks
- WHEN the `show-notebooks` or `index` component is rendered
- THEN the UI displays the empty state message "No Notebooks, Yet."

#### Scenario: Notebook list rendering
- GIVEN a project with existing notebooks
- WHEN the `show-notebooks` component is rendered
- THEN the UI displays the notebooks
- AND applies any active filters

#### Scenario: Creation event dispatch
- GIVEN the `create-notebook` component
- WHEN a new notebook is successfully saved
- THEN the `notebook-created` event is dispatched to update the parent components
