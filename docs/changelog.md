# Changelog

## 2026-11-12

- Escape de comodines LIKE en la búsqueda de proyectos (⚡project-finder, ⚡show-projects): los caracteres especiales (`%` y `_`) ingresados por el usuario se tratan como texto literal en lugar de comodines de coincidencia total, evitando que la búsqueda devuelva todos los proyectos al ingresar `%` o coincidencias espurias con `_`.

## 2026-11-11

- Descarga síncrona de carpetas en ZIP y eliminación de código asíncrono muerto (`pages::projects.files.index`, `folder-card`, `DownloadFilesZipAction`): consolidación de la descarga de carpetas como operación síncrona mediante `DownloadFilesZipAction` (recursiva, contemplando el disco de cada archivo y compatible con S3). Se renombra `downloadFolderAsync` a `downloadFolder` en el componente y tarjeta de carpetas, se remueven mensajes que sugerían procesamiento en segundo plano y se eliminan el job muerto `CreateFolderZipJob`, sus notificaciones y tests.
- Herramienta MCP `update_task_description` (`FunWorkServer`, `app/Mcp/Tools/UpdateTaskDescriptionTool.php`, `app/Data/Mcp/UpdateTaskDescriptionData.php`): nueva herramienta que permite a agentes externos reemplazar la descripción (brief técnico en Markdown) de una tarea existente sin duplicarla. Valida autenticación, existencia de la tarea, autorización vía `TaskPolicy::update`, longitud máxima del campo (`65535` caracteres) y devuelve la misma estructura JSON que `update_task_status`.
- Retiro de `docs/tasks/` y migración del backlog y sprint a FunWork (`docs/README.md`, `docs/roadmap.md`): eliminación de `docs/tasks/backlog.md` y `docs/tasks/sprint-actual.md`. El backlog y el sprint actual pasan a gestionarse directamente en la aplicación FunWork (proyecto "FunWork"), donde los agentes consultan, crean y actualizan tareas a través del servidor MCP (`FunWorkServer`). Actualización de la documentación del proyecto y roadmap para reflejar el nuevo ciclo de trabajo.
- Renderizado de descripciones de tareas en Markdown y extractos acotados en tarjetas (`app/Models/Task.php`, `⚡show.blade.php`, `⚡show-tasks.blade.php`, `⚡task-board.blade.php`, `resources/css/app.css`): soporte de CommonMark seguro mediante los accessors `description_html` (con `html_input => 'strip'`, `allow_unsafe_links => false` y `max_nesting_level => 10`) y `description_excerpt` (texto plano squished de ~2 líneas con `line-clamp-2` en tarjetas de lista y tablero). La página de detalle formatea código monoespaciado y diagramas ASCII con scroll horizontal (`overflow-x-auto`), tablas, listas y títulos en modo claro/oscuro mediante `.markdown-content`, mientras que el textarea de edición preserva el Markdown crudo. Tests de Pest cubriendo renderizado, XSS (scripts, enlaces javascript y atributos) y componentes Livewire.

## 2026-11-10

- Deploy manual seguro en `scripts/deploy.sh` (backup previo a migración y migración previa a levantar la app): nuevo orden en el bloque remoto por SSH que aborta ante cualquier fallo antes de afectar los contenedores en ejecución (`set -euo pipefail`):
  1. Construcción de `funwork:<sha>` y exportación de `APP_IMAGE="funwork:<sha>"`.
  2. Preflight de base de datos: levanta MySQL (`up -d mysql`) y reintenta con `mysqladmin ping` hasta que esté listo.
  3. Backup transaccional en `~/funwork/backups/backup_YYYYMMDD_HHMMSS_<sha>.sql.gz` vía `mysqldump` comprimido con gzip directo a archivo temporal `.tmp`, renombrando solo en éxito y conservando únicamente los 10 backups más recientes coincidentes con `backup_*.sql.gz`.
  4. Migración en un contenedor one-off descartable (`run --rm --no-deps -T app php artisan migrate --force </dev/null`) con la nueva imagen.
  5. Recreación del stack solo tras migrar (`up -d --remove-orphans`) y remoción de la migración post-up.
  6. Sincronización de `docs/` y limpieza de imágenes Docker conservando la versión activa, `latest` y la imagen previa para que el rollback funcione (una única versión previa).
  Documentación del nuevo flujo, comandos de restauración manual de base de datos y rollback de versión en `docs/01-architecture.md`.

## 2026-11-09

- Tokens personales de API y servidor MCP (`POST /mcp/funwork`, `FunWorkServer`, `pages::settings.integrations`): tarjeta "API Tokens (MCP)" en Settings → Integrations para crear tokens con prefijo `fw_` (mostrados en texto plano una única vez y guardados como hash SHA-256 en `api_tokens`), listarlos con fecha de último uso y revocarlos; autenticación Bearer mediante `AuthenticateMcpWithApiToken` y exposición de 7 herramientas MCP (`list_projects`, `list_tasks`, `get_task`, `update_task_status`, `add_task`, `add_inbox_item`, `read_notebook`) autorizadas con las Policies vigentes. Ver [ADR-003](decisions/ADR-003-mcp-server-and-api-tokens.md).

## 2026-11-08

- Importador de tareas desde NotebookLM en el tablero de tareas (`projects.tasks.import-tasks`, `⚡task-board`): modal Flux accesible desde el botón "Import" en la barra de filtros del tablero para crear tareas en lote a partir del formato Markdown de NotebookLM (`- [ ] [AREA] Título — criterio — depende de: X`). Flujo en dos pasos con validación y vista previa en tabla (inspección de área, título, criterio, dependencia y advertencias de duplicados o áreas desconocidas), importación transaccional de líneas válidas con asignación automática al usuario actual y cálculo de posición por estado.

## 2026-11-06

- Áreas por proyecto configurables (`Area`, `manage-areas`): cuatro áreas por defecto (Frontend, Backend, Database, Design) con paleta de badges Flux, asignación opcional en tareas, notebooks e inbox, badges en tarjetas y detalle, modal de gestión CRUD y reordenamiento para el dueño, y filtro reactivo por área en el tablero de tareas sin consultas N+1.
- Ajuste de zona horaria en el perfil de usuario (`⚡profile`): selector buscable de `DateTimeZone::listIdentifiers()` con detección y preselección automática de la zona horaria del navegador mediante Alpine e `Intl.DateTimeFormat` cuando el valor almacenado es UTC.
- Sincronización automática a Google Docs con debounce de 2 minutos (`SyncNotebookToGoogleDocJob`): encolado único por notebook ante modificaciones de páginas (`NotebookPage`) o reflejo de docs (`funwork:sync-docs`), actualizando `notebooks.content_changed_at` y limitándose a notebooks con Doc existente.
- Badge "Cambios sin sincronizar" en tarjetas de notebook (`⚡show-notebooks`): indicador visual en ámbar junto a la última sincronización cuando un notebook con Google Doc tiene cambios posteriores a su sincronización, desapareciendo al sincronizar.
- Página pública `/privacy` (ruta `privacy`) con la política de privacidad que exige la pantalla de consentimiento de Google para publicar la app: datos que guarda FunWork, uso del scope `drive.file` solo para los Docs que crea la app, tokens cifrados, Limited Use y cómo desconectar Google. Con la app publicada, el refresh token ya no vence a los 7 días.

## 2026-11-06

- Reemplazo de "Project pack" por exportación y sincronización con Google Docs por notebook: descarga `.md` por notebook (`GET /projects/{project}/notebooks/{notebook}/export`, `NotebookExportController`) y sincronización individual a su propio Google Doc desde el menú contextual de cada tarjeta de notebook (`⚡show-notebooks`). Migración de `google_doc_id` y `google_doc_synced_at` de `projects` a `notebooks`. Se eliminan las acciones y botones globales de Project pack. Ver ADR-004.

## 2026-11-05

- Visión a largo plazo: FunWork como hub de contexto con módulos de IA (Cerebro, Código, Diseño de UI y Diseño de base de datos), principios (app local no es modelo local, el servidor nunca usa la suscripción de un usuario, puertos y adaptadores, BYOK o agentes locales) y orden de construcción (MCP, `AiProvider` con BYOK, companion local). Nueva Fase 5 en el backlog.
- Sincronizar el Project pack con Google Docs: cada usuario conecta Google en Settings → Integrations (OAuth con Socialite, scope `drive.file`, tokens cifrados en `google_accounts`). El botón "Sincronizar con Google Docs" de la página del proyecto crea el Doc en su Drive la primera vez y después reemplaza su contenido sin cambiar el ID, así en NotebookLM alcanza con "Sync". Ver ADR-003 (commit bcbc03b).
- Export "Project pack": botón "Descargar Project pack" en la página del proyecto (`GET /projects/{project}/pack`, solo el dueño). Descarga un `.md` con las tareas agrupadas por estado (prioridad, vencimiento y asignados) y los notebooks no archivados con sus páginas en orden, listo para subir a NotebookLM como una sola fuente. Las decisiones entran por las páginas del notebook "Docs" (`docs/decisions/`) (commit c02c650).
- Deploy manual con `scripts/deploy.sh`: tests y lint en local, y por SSH build de la imagen en el VPS, `up`, `migrate` y `funwork:sync-docs`, sin GitHub Actions ni GHCR. La cuenta de GitHub quedó bloqueada por un pago de US$1,29 que no se pudo cobrar. `compose.prod.yml` pasa a usar por defecto la imagen local `funwork:latest` (`deploy.yml` sigue pasando `APP_IMAGE`, así que no le afecta). Se agrega `.deploy.env` a `.gitignore` (commit 49897e6).
- Fix en `scripts/deploy.sh`: el bloque remoto se ejecuta dentro de una función `main`. Antes `docker compose exec` leía el resto del script desde stdin (`bash -s`), así que después de `migrate` el deploy terminaba sin error y sin sincronizar los docs ni limpiar las imágenes viejas (commit 81f027d).
- Fix en `scripts/deploy.sh`: la limpieza de `/tmp/funwork-docs` dentro del contenedor corre como root. `docker compose cp` deja los archivos con el uid del host y `appuser` no los podía borrar, así que el deploy fallaba después de sincronizar y el siguiente se cortaba antes de la sync (commit 012c135).

## 2026-11-04

- CI: se quita PHP 8.3 de la matriz de `tests.yml` y `composer.json` pasa a `"php": "^8.4.1"`. El `composer.lock` usa Symfony 8 (PHP >= 8.4.1) desde `d6c9f05`, así que el job `ci (8.3)` fallaba al instalar dependencias en cada push. Producción ya corre PHP 8.4 (FrankenPHP).
- Workflow `.github/workflows/sync-docs.yml`: en cada push a `develop` o `master` que toca `docs/**` (o a mano con `workflow_dispatch`) copia `docs/` al VPS por SSH, lo pasa al contenedor `app` y ejecuta `funwork:sync-docs` con el ID del secret `FUNWORK_DOCS_PROJECT_ID`; comparte el grupo de concurrencia `deploy-production` con el deploy (commit `e2a5f96`).
- Comando `funwork:sync-docs {project}` (`App\Console\Commands\SyncDocsCommand`): espejado de `docs/` (excluyendo `archive/`) en el notebook "Docs" del proyecto (obtenido/creado con `firstOrCreate`), upsert por `(notebook_id, source_path)` con orden alfabético y contenido `['title' => source_path, 'body' => file_get_contents]`, eliminación de páginas obsoletas con `source_path` no nulo y preservación de páginas manuales (`source_path` nulo); nueva migración para la columna `source_path` nullable con índice único compuesto en `notebook_pages`; cobertura con tests en `tests/Feature/Console/SyncDocsCommandTest.php` (commit `1d782a3`).
- Sprint 2 de la Fase 1 cerrado. Se adelanta el flujo NotebookLM de la Fase 2: espejo de `docs/` en el notebook "Docs" del proyecto FunWork (comando `funwork:sync-docs` + workflow de GitHub por SSH, sin API tokens), Export "Project pack" y sync con Google Docs.

## 2026-11-03

- Vencimientos según zona horaria del usuario (`App\Support\DateHelper::userToday`): cálculo de fecha local `now(auth()->user()->timezone ?? config('app.timezone'))->toDateString()` centralizado en helper reutilizable y método `$user->localDate()`; consultas de `urgentTasks` (`whereDate('due_date', '<=', $localToday)`) y `today_tasks_count` (`whereDate('due_date', $localToday)`) en `resources/views/pages/projects/⚡show.blade.php`; corrección del widget de urgentes (`resources/views/components/projects/dashboard/urgent-tasks.blade.php`) mostrando `$task->title` (antes `$task->name`) y etiquetas "Overdue" si la fecha es anterior al día local y "Due today" si es igual; cobertura con tests unitarios en `tests/Unit/Support/DateHelperTest.php` y de feature en `tests/Feature/Livewire/Pages/Projects/ShowDashboardTest.php` (commit `ba699d3`).

- Test de queries del tablero (`tests/Feature/Livewire/Projects/Tasks/TaskBoardQueryCountTest.php`): se fija que la cantidad de consultas no crece al aumentar la cantidad de tareas (4 vs 20 tareas por columna) ni con usuarios asignados (2 asignados por tarea); medición registrada de 6 queries fijas cuando no hay tareas ocultas (`hiddenCount = 0`) y 24 queries fijas cuando se monta el flyout de tareas ocultas (`hiddenCount > 0`), validando además la visualización de las 3 tareas visibles por columna (commit `6fee098`).
- Backlog: se descarta la tarea de eager loading del tablero (el tablero no muestra asignados y ya hace queries fijas) y se reemplaza por un test que fija la cantidad de queries.
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
