# Feature: API Tokens + MCP Server (`laravel/mcp`)

## Status
Completed (2026-11-09)

## Context & Goal
Expose FunWork as a Model Context Protocol (MCP) server using `laravel/mcp` authenticated via personal API tokens (SHA-256 hashed), closing the NotebookLM → FunWork → Antigravity loop so Antigravity can list projects/tasks, inspect tasks, transition task status, create tasks, add inbox items, and read project notebooks.

## Confirmed Decisions
1. **7 MCP Tools in v1:** `list_projects`, `list_tasks`, `get_task`, `update_task_status`, `add_task`, `add_inbox_item`, `read_notebook`.
2. **Task Listing Tool Name:** `list_tasks` (filtered by `project_id`, optional `status`, and optional `area` slug or `"none"`).
3. **Authentication & Storage:** Custom `api_tokens` table + `ApiToken` model with SHA-256 hashed tokens, managed in `Settings -> Integrations` (`settings/integrations`), authenticated via an Octane-safe Bearer token middleware (`AuthenticateMcpWithApiToken`).
4. **Task Update Scope:** Status-only transitions via `update_task_status(task_id, status)`.

## Tasks
- [x] Require `laravel/mcp` in `composer.json` and publish/create `routes/ai.php`.
- [x] Create `api_tokens` migration, `ApiToken` model (`#[Fillable]`, `#[Hidden]`), `ApiTokenFactory`, and `User::apiTokens()` relationship.
- [x] Create `CreateApiTokenAction` (returns plaintext token once + persisted `ApiToken`) and `AuthenticateMcpWithApiToken` middleware (Bearer token SHA-256 verification, `last_used_at` update, `Auth::setUser($user)`, Octane-safe).
- [x] Build the API tokens management UI in `Settings -> Integrations` (`resources/views/pages/settings/⚡integrations.blade.php`) with token creation, one-time plaintext reveal modal, list of tokens, and revoke confirmation modal.
- [x] Implement `FunWorkServer` (`app/Mcp/Servers/FunWorkServer.php`) and the 7 MCP tools (`app/Mcp/Tools/*`) enforcing `ProjectPolicy`, `TaskPolicy`, `InboxItemPolicy`, and `NotebookPolicy`.
- [x] Register `/mcp/funwork` HTTP route (with `AuthenticateMcpWithApiToken`) and `funwork` local server in `routes/ai.php`.
- [x] Write Pest feature & isolation tests covering token management, Bearer middleware auth, and all 7 MCP tools (including cross-user isolation).
- [x] Run Pint and Pest, update `docs/` (`01-architecture.md`, `roadmap.md`, `areas/backend.md`, `areas/database.md`, `areas/frontend.md`, `tasks/backlog.md`, `tasks/sprint-actual.md`, `changelog.md`, and `ADR-003`).
