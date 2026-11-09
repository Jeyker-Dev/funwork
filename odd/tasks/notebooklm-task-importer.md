# NotebookLM Task Importer

Objective: bulk-create tasks in a project by pasting task lines produced by NotebookLM in the backlog format (`- [ ] [AREA] Title — criterion — depende de: X`).
Branch: `develop` · Delivery: `ask-on-risk` · Tests: `./vendor/bin/pest`

## Specs

- **S1** — Line format: `- [ ] [AREA] Title — acceptance criterion — depende de: X`.
  - Optional prefix `- [ ]` or `- [x]`. `- [x]` imports as Done; anything else as Todo.
  - Separators: em dash `—`, en dash `–`, or ` -- `. The `depende de: X` part is optional; `depende de: -` means no dependency.
  - Trim whitespace and silently skip blank lines.
  - Optional area tag resolved with `Project::areaByKey()`.
- **S2** — Validation per line:
  - Errors (line not imported): missing title, missing acceptance criterion, title longer than 255 characters.
  - Warnings (line still imported): area tag not found (message: "Area 'QA' not found — will be imported without area"), title already exists in project (case-insensitive duplicate).
  - Maximum 50 non-blank lines per import (validation error on textarea).
- **S3** — Mapping & Persistence:
  - title ← Title.
  - description ← criterion; when dependency exists, append `\n\nDepende de: X`.
  - status ← Todo (or Done for `- [x]`); priority ← TaskPriority::Low.
  - area_id ← resolved area or null.
  - position ← max(position) for that project and status, plus 1, incremented for each imported task.
  - assigned user ← current user (synced via `task_user` pivot).
  - DB transaction + `Gate::authorize('create', [Task::class, $project])`.
- **S4** — Livewire SFC `projects.tasks.import-tasks`:
  - Flux modal triggered by "Import" button in filter bar of `⚡task-board`.
  - Step 1: textarea + "Preview" button.
  - Step 2: preview table showing area badge, title, criterion, dependency, errors/warnings, "Back" button, and "Import N tasks" button (imports only valid lines).
  - Post-import: toast "N tasks imported", modal close, dispatch `task-created`.
  - Authorization: `view` on mount, `create` on import action.
- **S5** — Docs: backlog, sprint-actual, changelog, areas/backend, roadmap.

## Tasks

| ID | Specs | Route | Status | Evidence |
|----|-------|-------|--------|----------|
| T1 | S1, S2 | inline | done | RED: class not found → GREEN: 13/13 `ParseTaskImportActionTest` (67 assertions) |
| T2 | S3 | inline | done | RED: class not found → GREEN: 2/2 `ImportTasksActionTest` (20 assertions) |
| T3 | S4 | inline | done | RED: 6 failing → GREEN: 6/6 `ImportTasksTest` (37 assertions) |
| T4 | S5 | inline | done | Updated `backlog.md`, `sprint-actual.md`, `changelog.md`, `backend.md`, `roadmap.md` |

## Log

- **L1** (2026-11-08): Created task spec and initiated T1 with test-first approach.
- **L2** (2026-11-08): Implemented `ParsedTaskLineData` DTO with `Wireable` interface and `ParseTaskImportAction` with support for all separators (`—`, `–`, ` -- `), prefixes (`- [ ]`, `- [x]`), area resolution via `Project::areaByKey()`, dependency extraction, and duplicate title detection. All 13 unit tests passing.
- **L3** (2026-11-08): Implemented `ImportTasksAction` with `Gate::authorize('create', [Task::class, $project])`, transactional bulk creation, sequential position increment per project and status, and current user assignment. All 2 unit tests passing.
- **L4** (2026-11-08): Created `projects.tasks.import-tasks` Livewire SFC via artisan with 2-step Flux modal and wired trigger button in `⚡task-board`. All 6 feature tests passing.
- **L5** (2026-11-08): Full suite verification (286 passed, 2 skipped, 1097 assertions) and Pint clean. Updated project documentation.
