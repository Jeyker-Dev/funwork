# Sprint actual

Tareas tomadas del backlog para ejecutar ahora. Las grandes abren un documento `odd/tasks/<feature>.md`.

**Sprint:** Fase 3 — MCP para Antigravity (iniciado el 2026-11-09).

## En curso

_Sin tareas en curso._

## Hechas en este sprint

- [x] [FRONTEND] Render task descriptions as Markdown and clamp them in cards — Detail view renders safe Markdown with styled code/tables and cards show clamped 2-line plain text — depende de: -
- [x] [BACKEND] API tokens + servidor MCP con `laravel/mcp` — Antigravity lista y actualiza tareas de FunWork — depende de: Fase 0, Áreas (`odd/tasks/mcp-server.md`)
- [x] [BACKEND] Importador de tareas desde NotebookLM (formato fijo pegado en un textarea) — pegar N líneas crea N tareas con área y criterio — depende de: Áreas
- [x] [DATABASE] Áreas por proyecto (frontend, backend, database, design; configurables) y etiqueta de área en tareas y notas — se puede filtrar el tablero por área — depende de: Fase 1 (commit `15b568a`)
- [x] [FRONTEND] Badge "Cambios sin sincronizar" en tarjetas de notebook — etiqueta ámbar junto a la última sincronización cuando un notebook vinculado a Google Docs tiene modificaciones pendientes — depende de: Sincronización automática con debounce (commit `f603437`)
- [x] [BACKEND] Sincronización automática de notebooks con debounce — job `SyncNotebookToGoogleDocJob` encolado con delay de 2 minutos para notebooks con Google Doc, disparado en cambios de página y `sync-docs`, más timestamp `content_changed_at` — depende de: Sincronización individual por notebook (commit `f603437`)
- [x] [FRONTEND] Ajuste de zona horaria en el perfil de usuario — selector Flux listbox con `DateTimeZone::listIdentifiers()` y preselección automática del navegador si el valor guardado es UTC — depende de: - (commit `f603437`)
- [x] [BACKEND] Reemplazar el "Project pack" por exportación y sincronización con Google Docs por notebook — descarga `.md` por notebook y sync individual desde el menú de cada tarjeta para elegir granularmente qué notas van a NotebookLM — depende de: Sincronizar el "Project pack" (commit `4c94434`)
- [x] [BACKEND] Sincronizar el "Project pack" con un Google Doc (OAuth con Google, API de Docs/Drive) — desde FunWork se actualiza el Google Doc y en NotebookLM basta con "Sync" para refrescar la fuente — depende de: Export "Project pack" (commit `bcbc03b`)
- [x] [BACKEND] Export "Project pack" en Markdown (notebooks, tareas, decisiones) — se descarga un `.md` listo para subir a NotebookLM — depende de: - (commit `c02c650`)
- [x] [DEVOPS] Workflow de GitHub que ejecuta `funwork:sync-docs` en el VPS en cada push que toca `docs/**` (reusa los secrets SSH del deploy) — un cambio en `docs/` aparece en FunWork sin pasos manuales — depende de: Comando sync-docs (commit `e2a5f96`)
- [x] [BACKEND] Comando `funwork:sync-docs {project}` — espeja `docs/` del repo (sin `docs/archive/`) en el notebook "Docs" del proyecto: una página por archivo, upsert por ruta y borrado de las páginas cuyo archivo ya no existe — depende de: - (commit `1d782a3`)

## Sprint anterior: Fase 1 — Integridad, sprint 2 (cerrado el 2026-11-04)

- [x] [BACKEND] Usar `users.timezone` en vencimientos — una tarea no aparece vencida antes de tiempo en zonas UTC-x — depende de: - (commit `ba699d3`)
- [x] [QA] Test que fija la cantidad de queries del tablero (`⚡task-board`) — la cantidad de queries no crece con la cantidad de tareas ni de asignados — depende de: - (commit `6fee098`)
- [x] [BACKEND] Escapar los comodines de `LIKE` en la búsqueda de archivos — buscar `%` o `_` no rompe los resultados — depende de: - (commit `0cd9d7e`)
- [x] [FRONTEND] Borrar tarea desde la página de detalle (modal de confirmación, TaskPolicy::delete, redirección al tablero) — depende de: Página de detalle (commit `5981fda`)

## Sprint anterior: Fase 1 — Integridad, sprint 1 (cerrado el 2026-11-02)

- [x] [FRONTEND] Página de detalle de tarea + rutas de tareas con `Route::livewire` — `/projects/{p}/tasks/{t}` muestra la tarea y permite editarla — depende de: Policies (commit `bc10ad8`)
- [x] [FRONTEND] Unificar el mapa de colores de estado de tareas — una sola fuente usada por los componentes — depende de: Enums (commit `0b76c24`)
- [x] [FRONTEND] Dashboard filtra por TaskStatus::Done en lugar de `'completed'` — las tareas Done no aparecen como urgentes ni en los contadores — depende de: Enums (commit `1a42a81`)
- [x] [BACKEND] Enums TaskStatus y TaskPriority validados en TaskForm y ReorderTasks — no se guarda un estado o prioridad inválidos; migración que normaliza `todo`/`low` en minúscula — depende de: - (commit `bb637b5`)

## Sprint anterior: Fase 0 — Seguridad (cerrado el 2026-11-02)


- [x] [BACKEND] Autorizar ítems ajenos en `projects.files.move-items-modal::move` (`MoveSelectionAction`) y en `pages::projects.files.index::downloadFolderAsync` (`DownloadFilesZipAction`) — el usuario B recibe 403/404 y los 3 tests de `PoliciesTest` pasan sin `skip` — depende de: - (commit `7d91ec7`)
- [x] [QA] Tests de aislamiento entre usuarios — Pest en verde: el usuario B recibe 403/404 al editar, archivar o borrar proyectos, al reordenar tareas (drag & drop) y al renombrar, mover o descargar en ZIP archivos y carpetas del usuario A; revisar antes qué cubre `PoliciesTest` (inbox y scoped bindings ya cubiertos; búsqueda global y dashboard van en su propia tarea de Fase 1) — depende de: Policies, arreglar los 5 tests (commit `368c132`)
- [x] [QA] Arreglar los 5 tests que ya fallaban (ShowProjects, UploadFileForm, Files/Index upload, ProfileUpdate ×2) — suite de Pest en verde — depende de: - (commit `2b29862`)
- [x] [BACKEND] Limitar la asignación de tareas y el selector de dueño al usuario actual — los formularios no listan otros usuarios (consulta `User::` en `components/projects/tasks/⚡create-task.blade.php`) — depende de: - (commit `0e5baee`)
- [x] [BACKEND] Filtrar el inbox por `user_id` (convertir y borrar) — no se puede convertir ni borrar un ítem ajeno — depende de: - (commit `7479a35`)
- [x] [BACKEND] Scoped bindings en las rutas anidadas — un notebook, página o archivo de otro proyecto en la URL devuelve 404 — depende de: Policies (commit `718bffa`)
- [x] [BACKEND] Policies para Project, Task, Notebook, NotebookPage, Folder, ProjectFile e InboxItem — toda ruta y acción `/projects/{project}/*` responde 403/404 a quien no es dueño — depende de: - (commit `45df993`)
- [x] [BACKEND] Cerrar el registro público — `/register` no permite registrarse en producción — depende de: - (commit `0d4c87b`)
