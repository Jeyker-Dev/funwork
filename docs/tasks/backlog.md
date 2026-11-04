# Backlog

Formato: `- [ ] [AREA] Título — criterio de aceptación — depende de: X`

Orden y motivos: ver [`../roadmap.md`](../roadmap.md).

## Fase 0 — Seguridad (urgente)

- [x] [BACKEND] Cerrar el registro público — `/register` no permite registrarse en producción — depende de: -
- [x] [BACKEND] Policies para Project, Task, Notebook, NotebookPage, Folder, ProjectFile e InboxItem — toda ruta y acción `/projects/{project}/*` responde 403/404 a quien no es dueño — depende de: -
- [x] [BACKEND] Scoped bindings en las rutas anidadas — un notebook, página o archivo de otro proyecto en la URL devuelve 404 — depende de: Policies (commit `718bffa`)
- [x] [BACKEND] Filtrar el inbox por `user_id` (convertir y borrar) — no se puede convertir ni borrar un ítem ajeno — depende de: - (commit `7479a35`)
- [x] [BACKEND] Limitar la asignación de tareas y el selector de dueño al usuario actual — los formularios no listan otros usuarios (consulta `User::` en `components/projects/tasks/⚡create-task.blade.php`) — depende de: - (commit `0e5baee`)
- [x] [QA] Tests de aislamiento entre usuarios — Pest en verde: el usuario B recibe 403/404 al editar, archivar o borrar proyectos, al reordenar tareas (drag & drop) y al renombrar, mover o descargar en ZIP archivos y carpetas del usuario A; revisar antes qué cubre `PoliciesTest` (inbox y scoped bindings ya cubiertos; búsqueda global y dashboard van en su propia tarea de Fase 1) — depende de: Policies, arreglar los 5 tests (commit `368c132`). Nota: Cuando exista editar/archivar/borrar proyecto, agregar su test de aislamiento.
- [x] [BACKEND] Autorizar ítems ajenos en `projects.files.move-items-modal::move` (`MoveSelectionAction`) — el usuario B recibe 403/404 — depende de: - (commit `7d91ec7`)
- [x] [BACKEND] Autorizar ítems ajenos en `pages::projects.files.index::downloadFolderAsync` (`DownloadFilesZipAction`) — el usuario B recibe 403/404 — depende de: - (commit `7d91ec7`)

## Fase 1 — Integridad

- [x] [BACKEND] Enums TaskStatus y TaskPriority validados en TaskForm y ReorderTasks — no se guarda un estado o prioridad inválidos — depende de: - (commit `bb637b5`)
- [x] [FRONTEND] Dashboard filtra por TaskStatus::Done en lugar de `'completed'` — las tareas Done no aparecen como urgentes ni en los contadores — depende de: Enums (commit `1a42a81`)
- [ ] [BACKEND] Usar `users.timezone` en vencimientos — una tarea no aparece vencida antes de tiempo en zonas UTC-x — depende de: -
- [x] [FRONTEND] Página de detalle de tarea + rutas de tareas con `Route::livewire` — `/projects/{p}/tasks/{t}` muestra la tarea y permite editarla — depende de: Policies (commit `bc10ad8`)
- [x] [FRONTEND] Borrar tarea desde la página de detalle (modal de confirmación, TaskPolicy::delete, redirección al tablero) — depende de: Página de detalle (commit `5981fda`)
- [ ] [BACKEND] Arreglar el ZIP de carpetas — se despacha el job, lee del disco real (S3) y el link llega por notificación visible en la UI — depende de: -
- [ ] [BACKEND] Mover la lógica de las vistas SFC a Actions/Forms (stats del dashboard, guardado del editor, posición de tareas) — las vistas no contienen consultas de negocio — depende de: -
- [x] [FRONTEND] Unificar el mapa de colores de estado de tareas — una sola fuente usada por los componentes — depende de: Enums (commit `0b76c24`)
- [~] [BACKEND] Tablero: eager loading de asignados y menos queries por columna — DESCARTADA el 2026-11-03: el tablero no muestra asignados y ya hace 6 queries fijas (`limit(3)` + `count()` por columna); se reemplaza por el test de cantidad de queries
- [ ] [QA] Test que fija la cantidad de queries del tablero (`⚡task-board`) — la cantidad de queries no crece con la cantidad de tareas ni de asignados — depende de: -
- [ ] [DEVOPS] Deploy: migrar antes de servir y hacer backup de la base de datos antes de migrar — el código nuevo nunca corre sobre un esquema viejo — depende de: -
- [x] [BACKEND] Escapar los comodines de `LIKE` en la búsqueda de archivos — buscar `%` o `_` no rompe los resultados — depende de: - (commit `0cd9d7e`)
- [ ] [BACKEND] Escapar los comodines de `LIKE` en la búsqueda de proyectos (`⚡project-finder`, `⚡show-projects`) usando el helper de escape — buscar `%` o `_` no devuelve todos los proyectos — depende de: Escapar LIKE en archivos
- [ ] [QA] Verificar la cobertura de tests de búsqueda global y del dashboard — tests de Pest en verde para ambos — depende de: -
- [x] [QA] Arreglar los 5 tests que ya fallaban (ShowProjects, UploadFileForm, Files/Index upload, ProfileUpdate ×2) — suite de Pest en verde — depende de: - (traída al sprint de Fase 0, commit `2b29862`)

## Fase 2 — Áreas y flujo NotebookLM

- [ ] [DATABASE] Áreas por proyecto (frontend, backend, database, design; configurables) y etiqueta de área en tareas y notas — se puede filtrar el tablero por área — depende de: Fase 1
- [ ] [BACKEND] Importador de tareas desde NotebookLM (formato fijo pegado en un textarea) — pegar N líneas crea N tareas con área y criterio — depende de: Áreas
- [ ] [BACKEND] Export "Project pack" en Markdown (notebooks, tareas, decisiones) — se descarga un `.md` listo para subir a NotebookLM — depende de: -
- [ ] [BACKEND] Sincronizar el "Project pack" con un Google Doc (OAuth con Google, API de Docs/Drive) — desde FunWork se actualiza el Google Doc y en NotebookLM basta con "Sync" para refrescar la fuente — depende de: Export "Project pack"

## Fase 3 — MCP para Antigravity

- [ ] [BACKEND] API tokens + servidor MCP con `laravel/mcp` — Antigravity lista y actualiza tareas de FunWork — depende de: Fase 0, Áreas
- [ ] [DOCS] Retirar `docs/tasks/*.md` cuando el MCP esté en uso — el backlog vive solo en FunWork — depende de: MCP

## Fase 4 — Brain, notebooks y diseño

- [ ] [BACKEND] Módulo Brain / Decisiones (ADRs y notas de análisis por proyecto) — se crean y listan decisiones por proyecto — depende de: Fase 2
- [ ] [FRONTEND] Editor Markdown para las páginas de los notebooks — las páginas se editan y renderizan como Markdown — depende de: -
- [ ] [DESIGN] Módulo Diseño: links de Stitch y `DESIGN.md` por pantalla — cada pantalla del proyecto tiene su referencia — depende de: -
- [ ] [FRONTEND] Captura rápida global con atajo de teclado — desde cualquier vista se manda una idea al inbox de un proyecto — depende de: -
- [ ] [BACKEND] Digest diario con el scheduler — mail o notificación con los vencimientos del día — depende de: Timezone

## Documentación

- [ ] [DOCS] Completar las secciones "(por confirmar)" de `00-vision.md` — problema, usuario y objetivos definidos — depende de: -
- [ ] [DESIGN] Vincular las pantallas de Stitch en `areas/design.md` — cada pantalla con link o export — depende de: -

## Migrado desde OpenSpec

- `search-files-folders`: implementado (commit `0950bf6`), sin verify-report → ver la tarea de QA.
- `home-dashboard`: implementado (commits `31ebbd7`, `21f2c37`, `45fc729`), sin verify-report → ver la tarea de QA.
- `projects-tests`: completado y verificado (2026-07-10).
- `individual-context-menu`: archivado (2026-07-22).
