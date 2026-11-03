# Changelog

## 2026-11-03

- Escapar los comodines de `LIKE` en la búsqueda de archivos y carpetas (`pages::projects.files.index`): creación del helper reutilizable `App\Support\QueryHelper::escapeLike` para escapar `!`, `%` y `_` (usando `!` como carácter de escape y `whereRaw("name LIKE ? ESCAPE '!'", ...)` compatible con SQLite y MySQL); cobertura con tests unitarios en `tests/Unit/Support/QueryHelperTest.php` y pruebas en `tests/Feature/Livewire/Projects/Files/IndexTest.php` para `%`, `_` (archivos y carpetas), regresión de texto normal y `!` (commit `0cd9d7e`).
- Borrado de tareas desde la página de detalle (`pages::projects.tasks.show`): botón "Delete task" con modal de confirmación Flux (`<flux:modal>` y `<flux:modal.trigger>`), método `delete()` con autorización `TaskPolicy::delete`, eliminación en cascada de asignaciones y redirección con `navigate` a `project.tasks`; cobertura de tests en `tests/Feature/Livewire/Projects/Tasks/ShowTaskTest.php` (commit `5981fda`).

## 2026-11-02

- Backlog: nueva tarea en Fase 2 para sincronizar el "Project pack" con un Google Doc y refrescar la fuente de NotebookLM con "Sync".
- Migración de rutas de tareas a `Route::livewire` (índice `'project.tasks'` y detalle `'show.task'` con `scopeBindings` y `can`), creación de componentes Livewire SFC nativos `pages::projects.tasks.index` (renderizando `task-board` con layout `app`) y `pages::projects.tasks.show` (visualización con métodos de color de `TaskStatus`, metadatos y edición con botón explícito "Save changes"), extensión de `TaskForm` con `setTask` y `update` (autorizado con `TaskPolicy` y reutilizando reglas de validación), eliminación de vistas obsoletas en `resources/views/app/projects/tasks/` y cobertura de pruebas en `tests/Feature/Livewire/Projects/Tasks/ShowTaskTest.php` (commit `bc10ad8`).
- Centralización del mapa de colores y clases CSS por estado de tarea en métodos del Backed Enum `TaskStatus` (`app/Enums/TaskStatus.php`: `dot`, `columnBorder`, `columnBg`, `badge`, `cardClasses`, `text`, `accent`, `fluxColor`), inclusión de la directiva `@source '../../app/Enums';` en `resources/css/app.css` para el escaneo de Tailwind CSS v4, refactorización de `⚡task-board`, `⚡create-task`, `⚡task-more-indicator` y `⚡slider-tasks` sin cambios visuales, y cobertura de pruebas unitarias y de componentes en Pest (commit `0b76c24`).
- Filtrado del dashboard de proyecto (`resources/views/pages/projects/⚡show.blade.php`) usando `TaskStatus::Done->value` en lugar del literal `'completed'`, excluyendo las tareas finalizadas de `urgentTasks` y de las métricas `tasks_count` y `today_tasks_count`, con prueba en `tests/Feature/Livewire/Pages/Projects/ShowDashboardTest.php` (commit `1a42a81`).
- Creación de los Backed Enums `TaskStatus` (`Todo`, `Doing`, `Done`) y `TaskPriority` (`Low`, `Medium`, `High`) con casts en `Task`, validación con `Rule::enum` en `TaskForm` y `ConvertInboxToTaskForm`, validación previa a la transacción con `TaskStatus::tryFrom` en `ReorderTasks`, migración para normalizar valores en minúscula y defaults del esquema, reemplazo de literales en componentes de tareas y cobertura de tests en Pest (commit `bb637b5`).
- Validación de pertenencia al proyecto (`abort_unless(..., 404)`) en `MoveSelectionAction` (incluyendo `targetFolderId` antes de mutar y cada ítem de `selectedIds`) y en `DownloadFilesZipAction` (todos los `selectedIds`: archivos individuales, múltiples y carpetas); se quitan los 3 `skip` en `tests/Feature/Authorization/PoliciesTest.php` y se suman tests para destino ajeno y descarga ZIP propia en disco local (commit `7d91ec7`).

## 2026-11-01

- Extensión de pruebas de aislamiento en `tests/Feature/Authorization/PoliciesTest.php` para mover archivos y carpetas ajenos (`projects.files.move-items-modal::move`) y descargar carpetas ajenas en ZIP (`pages::projects.files.index::downloadFolderAsync`); los 3 escenarios quedan marcados con `skip` por hallazgos abiertos donde las acciones ignoran silenciosamente ítems de otro proyecto y devuelven 200 (o fallan con `FileNotFoundException` en el ZIP vacío) en lugar de responder 403/404 (commit `368c132`).
- Ajuste de los 5 tests que fallaban en `master` (`ProfileUpdateTest` ×2 con `username` explícito, `UploadFileFormTest` y `Projects/Files/IndexTest` usando `config('filesystems.default')`, y `ShowProjectsTest` verificando `$user->display_name`), dejando la suite de Pest en verde (commit `2b29862`).
- Validación server-side de `assignedUserIds.*` contra `auth()->id()` en `TaskForm`, remoción de `User::all()` en `components/projects/tasks/⚡create-task.blade.php` para que el selector solo exponga al usuario autenticado, y pruebas en Pest para la asignación de tareas y el selector/validación de dueño de proyecto (`CreateTaskTest.php` y `CreateProjectTest.php`).
- Los modales del inbox (borrar, convertir a tarea y a notebook) buscan el ítem filtrando por `user_id` y responden 404 si es ajeno, en vez de 403, para no revelar que el ID existe. Test: `tests/Feature/Authorization/PoliciesTest.php`.

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
