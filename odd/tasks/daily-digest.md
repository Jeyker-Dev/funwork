# Daily Digest (In-App Bell & Web Push)

FunWork Task: `#28` — `[Backend] Daily digest: in-app bell + Web Push`
Branch: `feat/daily-digest`

## Specs

### Implementation Brief Summary
- **Goal:** Provide each user with an automated morning digest of their workload across all non-archived projects via an in-app notification bell (`database` channel) and browser Web Push (`laravel-notification-channels/webpush`).
- **Content & Aggregation Rules:**
  - Strict scope: non-archived projects owned by the user (`$user->projects()->whereNull('archived_at')`).
  - Exclude tasks with `status === TaskStatus::Done`.
  - Sections based on user's local today (`DateHelper::userToday($user)` / `$user->localDate()`):
    - Overdue tasks: open tasks with `due_date < user_local_today`.
    - Tasks due today: open tasks with `due_date == user_local_today`.
    - Tasks due soon (next 3 days): open tasks with `due_date > user_local_today` and `due_date <= user_local_today + 3 days`.
    - Unprocessed inbox items across active owned projects.
  - Task deep link: `route('show.task', ['project' => $task->project_id, 'task' => $task->id])`.
  - Inbox link: `route('project.inbox', ['project' => $item->project_id])`.
  - Suppression rule: if all 4 sections are empty, do not create or send a notification.
- **Timezone-Correct Scheduling:**
  - `routes/console.php`: `Schedule::command(SendDailyDigestCommand::class)->hourly()`.
  - Command signature: `funwork:send-daily-digest {--user=} {--force}`.
  - Iterates users with `digest_enabled = true` (unless `--user` + `--force` overrides or as specified), resolves user local time `now($user->timezone ?? config('app.timezone'))`, checks local hour === `digest_hour` (unless `--force`), checks `last_digest_sent_at` in user's local timezone !== `user_local_today` (unless `--force`).
  - Dispatches `SendUserDailyDigestJob` (or processes synchronously / queued) and sets `last_digest_sent_at` (including when suppressed for being empty, so hourly runs do not re-evaluate the user all day).
- **In-App Notification Bell & Notifications Page:**
  - `App\Notifications\DailyDigestNotification` (`ShouldQueue`), `database` channel + conditional `WebPushChannel::class`.
  - Database payload links to `route('notifications.index')`.
  - Livewire SFC `layout/notification-bell` (`resources/views/components/layout/⚡notification-bell.blade.php`) integrated alongside the user menu in `layouts/app/header.blade.php`, `layouts/app/sidebar.blade.php` (mobile header), and `components/projects/home/⚡header.blade.php`.
  - Notifications page (`notifications.index`) listing notifications with mark-as-read / mark-all-as-read actions.
- **Web Push Architecture:**
  - Install `laravel-notification-channels/webpush` (only approved new dependency).
  - Publish `push_subscriptions` migration and add `HasPushSubscriptions` to `User`.
  - Service worker at `public/sw.js` (scope `/` at web root; no Caddyfile/compose changes).
  - Explicit user permission trigger in Settings → Notifications (`/settings/notifications`), note for iOS Safari installed PWAs, `POST /settings/notifications/push-subscription` via `App\Actions\Notifications\SavePushSubscriptionAction`, minimal push payload (`icon: /apple-touch-icon.png`).
  - `.env.example` gets empty `VAPID_PUBLIC_KEY=`, `VAPID_PRIVATE_KEY=`, `VAPID_SUBJECT=`.

### Corrections Applied (Precedence over Brief)
1. Ownership column is `owner_id` (`$user->projects()->whereNull('archived_at')`), not `user_id`.
2. Inbox route is `project.inbox`; task route is `show.task`.
3. Layout is `resources/views/layouts/app.blade.php` / `resources/views/layouts/app/` (and `layouts/home/` on projects index). No `components/layouts/⚡app.blade.php`.
4. Generate Livewire SFCs via `php artisan make:livewire`.
5. Push icon is `/apple-touch-icon.png`.
6. Database-backed tests go in `tests/Feature`.
7. Do not modify `compose.prod.yml` or Caddyfile; `public/sw.js` at web root has scope `/`.
8. Drop `notifications.digest`; link notifications to `notifications.index`.
9. `.env.example` only has empty `VAPID_*` keys; never modify `.env`; document `php artisan webpush:vapid --show` in docs without running it in production.
10. Reuse `DateHelper::userToday()` / `User::localDate()` and extract shared due-today/overdue query logic with the project dashboard so date math is not duplicated.
11. `SendDailyDigestCommand` signature `funwork:send-daily-digest {--user=} {--force}`; mark `last_digest_sent_at` even when suppressed for being empty.
12. Add iOS Safari installed-PWA note in Settings UI and docs; PWA manifest is out of scope.
13. Fix stale lines in `docs/roadmap.md` (Phase 3/4 `log_decision` and Markdown editor are already done).

## Tasks
- [x] **Slice 1 — Domain, Digest Data Builder & User Settings**:
  - Migration adding `digest_enabled`, `digest_hour`, `last_digest_sent_at` to `users`.
  - Shared due-today / overdue query scopes on `Task` (reused by `Project::dashboardStats()`, `⚡show.blade.php`, and `BuildDailyDigestAction`).
  - DTO `App\Data\DailyDigestData` and invokable Action `App\Actions\Notifications\BuildDailyDigestAction`.
  - Settings page `pages::settings.notifications` (`settings/notifications`, nav link in `settings/layout.blade.php`).
  - Feature tests (`BuildDailyDigestActionTest`, `NotificationSettingsTest`, regression on `ProjectStatsTest`), Pint, docs.
- [x] **Slice 2 — Hourly Scheduler Command, Database Notification, In-App Bell & Notifications Page**:
  - **2a (`5007151`):** `App\Notifications\DailyDigestNotification` (database channel) + `App\Jobs\SendUserDailyDigestJob` + `App\Actions\Notifications\DispatchDailyDigestAction` + `App\Console\Commands\SendDailyDigestCommand` + `routes/console.php` hourly schedule + `notifications.index` route + backend tests + `docs/areas/backend.md`.
  - **2b (`affac83`):** `layout/notification-bell` SFC + integration in headers + `pages/notifications/⚡index.blade.php` + UI tests + `docs/areas/frontend.md`, `docs/changelog.md`, `docs/roadmap.md`.
- [x] **Slice 3 — Web Push Integration & Browser Service Worker**:
  - **3a (`460e5c8`):** Install `laravel-notification-channels/webpush`, publish `push_subscriptions` migration, configure `HasPushSubscriptions` on `User`, add empty `VAPID_*` vars to `.env.example`, `SavePushSubscriptionAction` + `PushSubscriptionController` + form requests + `WebPushChannel::class` / `toWebPush()` on `DailyDigestNotification`, `PushSubscriptionTest` + `DailyDigestNotificationTest` WebPush coverage, `docs/areas/backend.md`.
  - **3b (`4d02659`):** `public/sw.js` service worker + browser opt-in card + iOS Safari Home Screen note in `⚡notifications.blade.php`, `NotificationSettingsTest` VAPID visibility coverage, `docs/areas/frontend.md`, `docs/changelog.md`, `docs/roadmap.md`.

## Log
- 2026-11-17: Created branch `feat/daily-digest`, registered FunWork tasks `#28` (Doing) and `#29` (Todo), initialized ODD task file.
- 2026-11-17: Completed Slice 1 (`f70b608`: user digest migration, shared `Task` due/open scopes, `DailyDigestData`, `BuildDailyDigestAction`, `pages::settings.notifications`, feature tests, docs).
- 2026-11-18: Committed Slice 2a (`5007151`: backend scheduler, job, command, database notification, `notifications.index` route, tests, `backend.md`).
- 2026-11-18: Committed Slice 2b (`affac83`: notification bell, notifications index page, layout headers, UI tests, docs). Committed with `--no-verify` after maintainer approved bypassing GGA's false-positive finding on pre-existing `routeIs(...)` strings in `header.blade.php` and `sidebar.blade.php`.
- 2026-11-18: Committed pre-Slice-3 refactor (`c2ea59f`: unified `SendDailyDigestCommand` single flow, removed redundant `ShouldQueue`/`Queueable` from `DailyDigestNotification`). Committed with `--no-verify` after maintainer rejected GGA findings targeting unchanged lines (`McpPresenter` vs digest presenter and `$dispatched++` counter in `lazyById` loop).
- 2026-11-18: Maintainer explicitly authorized `git commit --no-verify` for Slice 3a (`460e5c8`) and Slice 3b (`4d02659`).
- 2026-11-18: Verified live UI via `php -S 127.0.0.1:8787 -t public` (ephemeral SQLite + VAPID keys, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=file`) and `funwork:send-daily-digest --user=1 --force`: header bell badge (`2` -> `1` after mark as read), `/notifications` cards (`Overdue`, `Due today`, `Inbox`), `/settings/notifications` cards + iOS note, `/sw.js` (`200 OK`), and zero JS/console errors (Vite dev URL `127.0.0.1:5173` refused only because leftover `public/hot` had no `pnpm run dev` running). Fixed `<flux:callout>` heading rendering so Web Push errors (e.g. headless Chromium `Registration failed - push service not available`) display visibly; live FCM subscription delivery in a push-capable desktop browser remains unverified locally without real browser push permission/FCM service.
