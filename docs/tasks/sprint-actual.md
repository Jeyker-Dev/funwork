# Sprint actual

Tareas tomadas del backlog para ejecutar ahora. Las grandes abren un documento `odd/tasks/<feature>.md`.

**Sprint:** Fase 0 — Seguridad (aprobado el 2026-10-31)

## En curso

Orden de ejecución: de arriba hacia abajo. Los 5 tests se arreglan antes de la QA de aislamiento para que "Pest en verde" sea verificable.

- [ ] [BACKEND] Limitar la asignación de tareas y el selector de dueño al usuario actual — los formularios no listan otros usuarios (consulta `User::` en `components/projects/tasks/⚡create-task.blade.php`) — depende de: -
- [ ] [QA] Arreglar los 5 tests que ya fallaban (ShowProjects, UploadFileForm, Files/Index upload, ProfileUpdate ×2) — suite de Pest en verde — depende de: -
- [ ] [QA] Tests de aislamiento entre usuarios — Pest en verde: el usuario B recibe 403/404 al editar, archivar o borrar proyectos, al reordenar tareas (drag & drop) y al renombrar, mover o descargar en ZIP archivos y carpetas del usuario A; revisar antes qué cubre `PoliciesTest` (inbox y scoped bindings ya cubiertos; búsqueda global y dashboard van en su propia tarea de Fase 1) — depende de: Policies, arreglar los 5 tests

## Hechas en este sprint

- [x] [BACKEND] Filtrar el inbox por `user_id` (convertir y borrar) — no se puede convertir ni borrar un ítem ajeno — depende de: - (commit `7479a35`)
- [x] [BACKEND] Scoped bindings en las rutas anidadas — un notebook, página o archivo de otro proyecto en la URL devuelve 404 — depende de: Policies (commit `718bffa`)
- [x] [BACKEND] Policies para Project, Task, Notebook, NotebookPage, Folder, ProjectFile e InboxItem — toda ruta y acción `/projects/{project}/*` responde 403/404 a quien no es dueño — depende de: - (commit `45df993`)
- [x] [BACKEND] Cerrar el registro público — `/register` no permite registrarse en producción — depende de: - (commit `0d4c87b`)
