# Sprint actual

Tareas tomadas del backlog para ejecutar ahora. Las grandes abren un documento `odd/tasks/<feature>.md`.

**Sprint:** Fase 0 — Seguridad (aprobado el 2026-10-31)

## En curso

- [ ] [BACKEND] Limitar la asignación de tareas y el selector de dueño al usuario actual — los formularios no listan otros usuarios — depende de: -
- [ ] [QA] Tests de aislamiento entre usuarios — Pest en verde: el usuario B no accede a nada del usuario A — depende de: Policies

## Hechas en este sprint

- [x] [BACKEND] Filtrar el inbox por `user_id` (convertir y borrar) — no se puede convertir ni borrar un ítem ajeno — depende de: - (commit `7479a35`)
- [x] [BACKEND] Scoped bindings en las rutas anidadas — un notebook, página o archivo de otro proyecto en la URL devuelve 404 — depende de: Policies (commit `718bffa`)
- [x] [BACKEND] Policies para Project, Task, Notebook, NotebookPage, Folder, ProjectFile e InboxItem — toda ruta y acción `/projects/{project}/*` responde 403/404 a quien no es dueño — depende de: - (commit `45df993`)
- [x] [BACKEND] Cerrar el registro público — `/register` no permite registrarse en producción — depende de: - (commit `0d4c87b`)
