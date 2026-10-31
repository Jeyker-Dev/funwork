## Exploration: home-dashboard

### Current State
Currently, `projects.show` is a static Blade view (`resources/views/app/projects/show.blade.php`) returning empty placeholder boxes in a grid. The route is a static `Route::get` instead of a Livewire component. There are active Eloquent models for `Task`, `InboxItem`, `ProjectFile`, and `Notebook`. Timezones are currently entirely UTC by default and `due_date` is stored as a `date` column.

### Affected Areas
- `routes/web.php` — Must update `projects.show` to use a full-page Livewire route.
- `resources/views/pages/projects/⚡show.blade.php` — New page component orchestrator for the dashboard.
- `resources/views/components/projects/dashboard/` — New components for: Quick Capture, Stats, Urgent Tasks, Recent Activity.
- `app/Livewire/Forms/QuickCaptureForm.php` — Form Object to handle Inbox saving.

### Approaches
1. **Polymorphic Recent Activity Table** — Create a new `recent_activities` table with `morphTo`.
   - Pros: Easy to query, extensible to comments/tasks.
   - Cons: Overhead of tracking and syncing every file/notebook change via Observers.
   - Effort: High

2. **Union All Query** — `Notebook::select('id', 'name', 'updated_at', DB::raw('"notebook" as type'))->unionAll(ProjectFile::select('id', 'name', 'updated_at', DB::raw('"file" as type')))`
   - Pros: Simple, no schema changes needed, perfectly suits the 5 items requirement.
   - Cons: Slightly complex raw query builder syntax.
   - Effort: Low

### Recommendation
Use the **Union All Query** approach. Since we only need 5 recent items and the schemas for `Notebook` and `ProjectFile` have compatible baseline columns (`id`, `name`, `project_id`, `updated_at`), a union is performant enough and requires no new tables or observers. For the layout, we should upgrade `projects.show` to an anonymous Livewire component following the `⚡show.blade.php` convention, and decompose the sections (Quick Capture, Stats, Tasks, Recent) into separate anonymous components inside `resources/views/components/projects/dashboard/`.

### Risks
- `due_date` being just a `date` column (and timezone set to UTC) means timezone offsets could cause a task to appear "overdue" or "today" incorrectly for users in distant timezones.
- The `Union All` query needs careful aliasing to not drop Eloquent properties if we attempt to hydrate models. Returning a simple array or standard objects is safer for display.

### Ready for Proposal
Yes — The user's requirements are clear and perfectly map to our Livewire + Flux stack conventions.
