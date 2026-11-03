# Sprint actual

Tareas tomadas del backlog para ejecutar ahora. Las grandes abren un documento `odd/tasks/<feature>.md`.

**Sprint:** Fase 1 — Integridad (aprobado el 2026-11-02)

## En curso

Orden de ejecución: de arriba hacia abajo. Las tareas 2 y 3 dependen de los enums.

- [ ] [FRONTEND] Página de detalle de tarea + rutas de tareas con `Route::livewire` — `/projects/{p}/tasks/{t}` muestra la tarea y permite editarla — depende de: Policies

## Hechas en este sprint

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
