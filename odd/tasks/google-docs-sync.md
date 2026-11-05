# Google Docs sync for the Project pack

Objective: each user connects their Google account, and FunWork publishes the project's Project pack to a Google Doc in that user's Drive so that NotebookLM refreshes the source with "Sync".
Branch: `develop` (the user makes the commits) · Delivery: `ask-on-risk` · Tests: `./vendor/bin/pest`

## Specs

- **S1** — Backlog: "Sincronizar el "Project pack" con un Google Doc (OAuth con Google, API de Docs/Drive) — desde FunWork se actualiza el Google Doc y en NotebookLM basta con "Sync" para refrescar la fuente".
- **S2** — The user chose "OAuth por usuario": each user connects and disconnects their own Google account. Tokens are stored encrypted, and the refresh token is used to renew access.
- **S3** — Syncing a project builds the content with `BuildProjectPackAction`, the same content as the `.md` download.
  - First sync: creates a Google Doc in the user's Drive, converting from Markdown.
  - Later syncs: replace the content of that same Doc, keeping the same ID, so the NotebookLM source stays the same.
  - If the Doc was deleted, a new one is created.
- **S4** — Only the project owner can sync. If the user has not connected Google, they are sent to connect first.
- **S5** — Docs: setup steps in Google Cloud (OAuth client, Drive API, consent screen in production so the refresh token does not expire after 7 days) and the new variables in `.env.example`.

Assumptions (not asked for by the user, chosen to reduce risk):
- Scope `drive.file`: access only to the files the app creates. It is not sensitive and does not need Google verification.
- `laravel/socialite` for the OAuth flow (state/CSRF), and the Laravel `Http` client for Drive, without `google/apiclient`.
- The sync runs synchronously, triggered by the user, with a toast as feedback.
- Connect and disconnect live in Settings → "Integraciones".

## Tasks

| ID | Specs | Route | Status | Evidence |
|----|-------|-------|--------|----------|
| T1 | S2 | inline | done | RED: 9 failing (RouteNotFound) → GREEN 9/9 `GoogleConnectionTest` |
| T2 | S3 | inline | done | No RED: the client was written in T1 (needed for revoke); 8/8 `GoogleDriveClientTest` |
| T3 | S3, S4 | inline | done | RED 6/7 → GREEN 7/7 `SyncProjectPackToGoogleDocTest` |
| T4 | S5 | inline | done (`.env.example` pending, edit not allowed) | ADR-003, areas backend/database, changelog, sprint, backlog |

- T1: Google connection — config, `google_accounts` table (encrypted tokens), model, Socialite redirect/callback, Settings → Integraciones with connect/disconnect (revoke).
- T2: Drive client — token refresh, create Doc from Markdown, replace content, 404 → recreate.
- T3: Sync — `projects.google_doc_id` / `google_doc_synced_at`, `SyncProjectPackToGoogleDocAction`, button and Doc link on the project page.
- T4: Docs — `.env.example`, Google Cloud guide, areas, changelog, sprint.

Route: inline for all tasks. The writer trigger does not fire: there are no parallel units with disjoint surfaces, because T2 and T3 depend on T1.
Risk: item 2 (OAuth tokens, credentials) and item 5 (new dependency `laravel/socialite`) → independent verifier at the end of the feature.

## Log

- **L1** (2026-11-05, user): "hagamos la 2 y 3 en conjunto ahora mismo primero haremos la 2 y luego la 3, por si falla una esta la otra entonces"
- **L2** (2026-11-05, user): "OAuth por usuario"
- **L3** (2026-11-05): Option 2 (Project pack `.md`) is implemented and its full suite is green, but it is not committed yet. The Drive "Manage uploads" guide confirms that Markdown imports into Google Docs and that an `update` with conversion "the full contents of the document are replaced".
- **L4** (2026-11-05): T1–T4 implemented. Full suite: 225 passed, 2 skipped; Pint clean. `.env.example` cannot be read or edited from the agent (permission denied), so the user adds `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`. Option 2 was committed as `c02c650`. Next: independent verifier (Risk items 2 and 5).
- **L5** (2026-11-05): Independent verifier found no blockers; S1–S4 met, S5 partial (`.env.example`). Advisory 1 applied: the callback also rejects backslashes in `google_return_to` (dataset test). Advisories 2–4 (concurrent refresh, extra write on a trashed Doc, Spanish toasts without `__()`) were left as they are. Suite 228 passed.
