# ADR-003: Tokens de API con SHA-256 y servidor MCP HTTP con `laravel/mcp`

- **Fecha:** 2026-11-09
- **Estado:** Aceptada

## Contexto

La Fase 3 del roadmap ([ADR-002](ADR-002-funwork-as-workflow-hub.md)) conecta agentes locales (Antigravity, Claude Code, Codex) con FunWork mediante el protocolo MCP (Model Context Protocol). FunWork corre en un VPS con Laravel Octane (FrankenPHP) y necesita autenticar peticiones MCP remotas por usuario sin exponer sesiones de navegador ni almacenar estado de usuario en propiedades estáticas entre requests.

## Decisión

1. **Almacenamiento propio de API tokens (`api_tokens`):** se crea el modelo `ApiToken` con tokens con prefijo `fw_` + 40 caracteres aleatorios. En base de datos solo se guarda el hash SHA-256 (`token` único de 64 caracteres), mostrando el valor en texto plano una única vez al crearlo en Settings → Integrations. No se añade `laravel/sanctum` para evitar dependencias adicionales innecesarias.
2. **Transporte MCP HTTP y local (`laravel/mcp`):** se registra `FunWorkServer` en `routes/ai.php` sobre `POST /mcp/funwork` protegido por el middleware `AuthenticateMcpWithApiToken` (y como servidor `local` bajo el handle `funwork`).
3. **Superficie de herramientas v1 (7 herramientas):** `list_projects`, `list_tasks` (filtrando por `project_id`, `status` y `area`), `get_task`, `update_task_status` (acotado al cambio de estado `Todo`/`Doing`/`Done`), `add_task`, `add_inbox_item` y `read_notebook` (reutilizando `BuildNotebookExportAction`). Todas las herramientas autorizan mediante las Policies existentes (`ProjectPolicy`, `TaskPolicy`, `InboxItemPolicy`, `NotebookPolicy`). `log_decision` se pospone a la Fase 4 cuando exista el módulo Brain / Decisiones.

## Consecuencias

- Antigravity puede conectarse a FunWork desde `~/.gemini/config/mcp_config.json` enviando `Authorization: Bearer fw_...`.
- La revocación de un token en Settings → Integrations corta el acceso del agente de forma inmediata.
- El middleware resuelve el usuario por request sin fugas de estado en Laravel Octane.
