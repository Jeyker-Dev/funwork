# Changelog

## 2026-10-31

- Scoped bindings (`scopeBindings()`) en las rutas anidadas de tareas y páginas de notebook (`routes/web.php`): una tarea, notebook o página de otro proyecto o notebook en la URL devuelve 404. La vista previa de archivos ya devolvía 404 desde el controlador. Test: `tests/Feature/Authorization/ScopedBindingsTest.php`.
- Policies de autorización (`app/Policies/*Policy.php`) para `Project`, `Task`, `Notebook`, `NotebookPage`, `Folder`, `ProjectFile` e `InboxItem`, aplicadas en rutas (`routes/web.php`), controlador de vista previa, páginas y componentes Livewire, Form Objects y Actions. Test: `tests/Feature/Authorization/PoliciesTest.php`.
- Registro público desactivado (`config/fortify.php`) hasta que existan las Policies. Test: `tests/Feature/Auth/RegistrationDisabledTest.php`.
- Auditoría del proyecto y roadmap por fases (`roadmap.md`), backlog cargado por fase y [ADR-002](decisions/ADR-002-funwork-as-workflow-hub.md): FunWork como hub del flujo. Se corrigió la documentación que afirmaba que existían Policies.
- Despliegue en producción con Docker: Laravel Octane sobre FrankenPHP (HTTPS automático), `Dockerfile` multi-stage, `compose.prod.yml` (app, queue, scheduler, MySQL) y `deploy.yml` que construye la imagen en GitHub Actions y la publica en GHCR.

## 2026-10-30

- Migración de la documentación a `docs/` (dos capas) y del flujo SDD a ODD. `openspec/` y `sdd/` quedan archivados en `docs/archive/`. Ver [ADR-001](decisions/ADR-001-docs-and-odd-workflow.md).

## Historial previo (resumen de git)

- **2026-08:** búsqueda global de archivos y carpetas; avatares en S3 con URLs firmadas.
- **2026-07:** menú contextual por ítem, lightbox de previsualización, renombrado con modal, mover archivos, tests de notebooks, archivos y proyectos.
- **Anteriores:** dashboard del proyecto, módulo inbox, tablero de tareas con drag & drop y varios asignados.
- **2026-03-21:** commit inicial (starter kit Laravel Livewire).
