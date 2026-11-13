# Brain / Decisions Module

## Goal
Per-project decision records (ADRs): create, list, view and edit them in FunWork.

## Scope
- **In**: `decisions` table + model, status lifecycle, list/show/create/edit UI (Livewire 4 SFC + Flux), safe Markdown rendering, owner-only authorization, sidebar link.
- **Out**: AI/Gemini analysis (task "AiProvider port"), MCP `log_decision` (follow-up), exporting to `.md` files, importing `docs/decisions/*.md`, deleting decisions (a decision is superseded, never deleted), area tagging.

## Data Model
`decisions`:
- `id`
- `project_id` (FK `projects`, cascade delete)
- `number` (int, sequential per project, `UNIQUE(project_id, number)`)
- `title` (string)
- `status` (backed enum `DecisionStatus`: `Proposed`, `Accepted`, `Rejected`, `Superseded`)
- `context`, `decision`, `consequences` (text, required, Markdown)
- `decided_at` (date, nullable)
- `superseded_by_id` (nullable FK to `decisions`, set null on delete; required when `status = Superseded` and must belong to the same project)
- `created_by` (FK `users`)
- `timestamps`

Relationships:
- `Project` `hasMany` `Decision`
- `Decision` `belongsTo` `Project`, `creator` (`User`), `supersededBy` (`Decision`)

## Implementation Spec
1. **Shared Markdown rendering**: Extract `Task::MARKDOWN_OPTIONS` + `Str::markdown` into `App\Support\Markdown::toHtml(?string): HtmlString` with the same options (`html_input => 'strip'`, `allow_unsafe_links => false`, `max_nesting_level => 10`). Refactor `Task::descriptionHtml` to use it. Decisions use the same helper and the existing `.markdown-content` CSS class.
2. **Migration, Model, Enum, Factory**: `php artisan make:migration`, `Decision` model with `DecisionStatus` enum + `date` casts, `DecisionFactory` with states per status.
3. **Policy**: `DecisionPolicy` mirroring `NotebookPolicy` (`$project->owner_id === $user->id`): `viewAny`/`create` on `Project`, `view`/`update` on `Decision`. No `delete`.
4. **Actions & Form**: Invokable Actions in `app/Actions/Decisions/` (`CreateDecisionAction` assigns `number = max + 1` inside `DB::transaction` with `lockForUpdate` on the project's decisions; `UpdateDecisionAction`). Validation lives in `app/Livewire/Forms/DecisionForm.php` shared by create and edit.
5. **Livewire SFCs**: Created via `php artisan make:livewire`:
   - `pages::projects.decisions.index` (list: number as `ADR-001`, title, status badge, `decided_at`; status filter)
   - `pages::projects.decisions.show` (three Markdown sections; link to the superseding decision)
   - Create/edit form page or modal reusing `DecisionForm`
6. **Routes & Sidebar**: Routes in `routes/web.php` under `/projects/{project}/decisions` following notebooks routes (`scopeBindings` + `can` middleware). Add "Decisions" to `resources/views/layouts/app/sidebar.blade.php` next to Notebooks.

## Tasks (Slices)
- [x] **Slice 1 — Shared Markdown helper + domain**: `App\Support\Markdown` helper + `Task` refactor, `decisions` migration, `Decision` model, `DecisionStatus` enum, `DecisionFactory`, `DecisionPolicy`, `CreateDecisionAction`, `UpdateDecisionAction`, policy + action + Task Markdown regression tests, docs.
- [x] **Slice 2 — List and show pages**: `pages::projects.decisions.index`, `pages::projects.decisions.show`, routes in `routes/web.php`, sidebar link in `sidebar.blade.php`, list filter + Markdown/XSS + policy isolation tests, docs.
- [ ] **Slice 3 — Create/edit with DecisionForm**: `DecisionForm`, create/edit UI, status transitions including `Superseded` (same-project constraint), validation + CRUD + Superseded + policy isolation tests, docs.

## Acceptance Criteria
- [ ] Migration creates `decisions` with the FKs and the unique `(project_id, number)` index.
- [ ] Owner sees their project's decisions listed with ADR number, title, status badge and date; status filter works.
- [ ] Show page renders context/decision/consequences as Markdown; `<script>`, `javascript:` links and HTML attributes are stripped.
- [ ] Owner can create a decision; numbers increment per project starting at 1, independently per project.
- [ ] Owner can edit content and status; `Superseded` requires a superseding decision from the same project.
- [ ] Another user gets 403/404 on list, show, create and edit of someone else's project decisions.
- [ ] Task description rendering is unchanged after extracting the Markdown helper.
