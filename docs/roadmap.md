# Roadmap

> Origen: auditoría y análisis del 2026-10-31. Las tareas concretas viven en [`tasks/backlog.md`](tasks/backlog.md); este documento explica el porqué y el orden.

## Principios

- **Tareas en FunWork, conocimiento en el repo.** Backlog, sprint y estado de tareas se guardan en la base de datos de FunWork (cuando exista el importador o el MCP). Visión, arquitectura y ADRs quedan en `docs/`, versionados con el código. Ver [ADR-002](decisions/ADR-002-funwork-as-workflow-hub.md).
- **Cada módulo produce datos estructurados y exportables a Markdown.** Ese export es el contexto para NotebookLM hoy y el dataset de los modelos por módulo en el futuro.
- **Seguridad antes que features.** Las Policies son además la base del multiusuario futuro.

## Fase 0 — Seguridad (urgente)

Estado al iniciar la fase (auditoría 2026-10-31): no hay Policies (`app/Policies` no existe), ninguna ruta `/projects/{project}/*` verifica el dueño (`routes/web.php:16-35`) y el registro público está abierto (`config/fortify.php:147`). Cualquier usuario verificado puede leer, editar o borrar proyectos ajenos. Los ítems del inbox se cargan con `findOrFail` sin filtrar por usuario.

Avance: **100% completada y cerrada** — registro cerrado (`0d4c87b`), Policies hechas (`45df993`), scoped bindings hechos (`718bffa`), inbox filtrado por `user_id` (`7479a35`), asignación de tareas limitada al usuario actual (`0e5baee`), suite de Pest en verde (`2b29862`), tests de aislamiento (`368c132`) y autorización 404 de ítems/carpetas ajenos en `MoveSelectionAction` y `DownloadFilesZipAction` sin `skip` en `PoliciesTest` (commit `7d91ec7`).

- Cerrar el registro público mientras no exista el multiusuario.
- Policies para `Project`, `Task`, `Notebook`, `NotebookPage`, `Folder`, `ProjectFile` e `InboxItem`.
- Scoped bindings en las rutas anidadas (notebook ∈ project, page ∈ notebook, file ∈ project).
- Inbox: todas las consultas filtradas por `user_id`.
- Asignación de tareas y selector de dueño: limitados al usuario actual.
- Tests de aislamiento: el usuario B recibe 403/404 en todo lo del usuario A.

## Fase 1 — Integridad de lo existente

- Enums `TaskStatus` (Todo/Doing/Done) y `TaskPriority`, validados en `TaskForm` y `ReorderTasks`. Corregir el filtro `'completed'` del dashboard (`pages/projects/⚡show.blade.php:33,38-39`).
- Usar `users.timezone` en vencimientos y en "hoy".
- Página de detalle de tarea (hoy la vista está vacía) y rutas de tareas con `Route::livewire`.
- Arreglar el ZIP de carpetas: despachar `CreateFolderZipJob`, leer del disco real (S3) y mostrar las notificaciones en la UI.
- Mover las consultas y reglas de las vistas SFC a Actions o Forms. Unificar el mapa de colores de estado (hoy está repetido en 5 componentes).
- Tablero: eager loading de asignados, menos queries por columna.
- Deploy: correr `migrate --force` antes de servir la nueva versión y hacer backup de la base de datos antes de migrar.
- Escapar los comodines de `LIKE` en la búsqueda de archivos.

## Fase 2 — Áreas y flujo NotebookLM

- **Áreas como dato** (`frontend`, `backend`, `database`, `design`, configurables por proyecto) para etiquetar tareas y notas.
- **Importador de tareas:** pegar la salida de NotebookLM en el formato `- [ ] [AREA] Título — criterio — depende de: X` y crear las tareas en lote.
- **Project pack:** exportar a Markdown los notebooks, las tareas y las decisiones del proyecto para subirlo a NotebookLM. Las páginas ya son JSON `{title, body}` en texto plano.

## Fase 3 — FunWork como servidor MCP para Antigravity

- API tokens + servidor MCP (`laravel/mcp`).
- Herramientas: `list_sprint_tasks`, `get_task`, `update_task_status`, `add_task`, `add_inbox_item`, `read_notebook`, `log_decision`.
- Antigravity se configura en `~/.gemini/config/mcp_config.json`. Al terminar una tarea, la marca en FunWork y `docs/tasks/*.md` se retira.

## Fase 4 — Brain, notebooks y diseño

- Módulo **Brain / Decisiones**: ADRs y notas de análisis por proyecto (el futuro "NotebookLM interno").
- Editor Markdown para las páginas de los notebooks.
- Módulo **Diseño**: links de Stitch y `DESIGN.md` por pantalla.
- Captura rápida global (atajo de teclado → inbox de cualquier proyecto).
- Digest diario con el scheduler (vencimientos y tareas del día).

## Fase 5 — Visión a largo plazo

- Multiusuario y clientes: `project_members` con roles sobre las Policies de la fase 0, y chat por proyecto.
- Módulos según `projects.type`: proyectos de música y de estudio.
- Un modelo de IA por módulo (Design, Database, Brain…), entrenado o con contexto a partir de los exports de cada módulo.

## Referencias externas

- Antigravity + MCP: <https://codelabs.developers.google.com/google-workspace-mcp-antigravity>
- NotebookLM Enterprise API (no hay API para cuentas personales): <https://docs.cloud.google.com/gemini/enterprise/notebooklm-enterprise/docs/api-notebooks-sources>
- Stitch MCP (comunidad, sin servidor oficial confirmado): <https://glama.ai/mcp/servers/mattferry/stitch-mcp-server/tree>
