# Livewire Conventions

Livewire and Flux APIs come from Laravel Boost. These are layout and
decomposition decisions only.

## Components

- Single-file components (PHP class and Blade in one file) are the default.
- Create components with `php artisan make:livewire`, never by hand.
- Full pages live in `resources/views/pages/<area>/`; reusable components in
  `resources/views/components/<domain>/`; shared small pieces (search input,
  avatar) in `resources/views/components/utilities/`.
- Layouts are Blade components, not Livewire components.

## Decomposition

- A page is an orchestrator: it loads data and composes children.
- If a template has more than three sections, each interactive section becomes
  its own component.
- Every modal is its own component.
- Every form with three or more fields, or non-trivial rules, gets a Form
  Object. Its `store()` runs: validate, persist, reset, then feedback (toast,
  close modal).
- A component around 80 lines is a signal to check the `clean-code` size
  causes.

## Communication

- Parent to child: props.
- Child to parent: dispatch a past-tense kebab-case event (`task-created`);
  the parent listens and re-renders.

## Livewire or Alpine

| Livewire (`wire:*`) | Alpine (`x-*`) |
|---|---|
| Persists data | Toggle, show or hide |
| Submits a form | Local feedback (disable a button) |
| Queries the database | Clipboard, drag, animation |
| Checks authorization | Theme switching, CSS transitions |
