# Backend

Laravel 13. Skills de referencia: `laravel-clean-architecture` y `pest-laravel-testing`.

## Convenciones

- La lógica de negocio va en `app/Actions/*` y en Form Objects (`app/Livewire/Forms`).
- Autorización: ninguna acción sin Policy (`app/Policies/*Policy.php`). Las rutas `/projects/{project}/*` usan middleware `can`, los componentes Livewire usan `$this->authorize()`, y los Form Objects/Actions usan `Gate::authorize()`. Las rutas anidadas usan `scopeBindings()` para que el hijo pertenezca al padre de la URL (404 si no).
- Storage siempre a través de los discos de Laravel, nunca con rutas absolutas.
- Tareas pesadas en Jobs encolados; el resultado se avisa con `DatabaseNotification`.
- Búsquedas `LIKE`: escapar comodines (`!`, `%`, `_`) mediante `App\Support\QueryHelper::escapeLike()` con `whereRaw("col LIKE ? ESCAPE '!'", ['%'.$escaped.'%'])` compatible con SQLite y MySQL (commit `0cd9d7e`).

## Reglas de negocio vigentes

### Proyectos (`ProjectForm`)
- Requeridos: `name`, `description`, `icon`, `type`, `status`, `ownerId`.
- `ownerId` debe coincidir con `auth()->id()` (de lo contrario aborta con 403); el selector de dueño solo ofrece al usuario autenticado.
- `dueDate` y `archiveDate` se validan como fecha; `archiveDate` se guarda en `archived_at`.

### Notebooks (`NotebookForm`)
- Requeridos: `project_id`, `created_by`. Al crear: toast, cierre del modal, reset del formulario y evento `notebook-created`.

### Archivos y carpetas
- Crear carpeta: el nombre es obligatorio y se valida.
- Borrado: prohibido si el archivo está en uso o en un proceso activo; en ese caso se devuelve un error explicativo.
- ZIP de selección: si falta algún archivo, se aborta de forma segura y se devuelve un error.
- ZIP de carpeta: asíncrono con `CreateFolderZipJob` → toast "Preparing download..." → notificación con el link (o con el fallo). **Estado actual: roto.** El job nunca se despacha, solo lee del disco `local` y las notificaciones no se muestran en la UI.
- Mover: no se permite mover al mismo directorio ni dentro de sí mismo o de una subcarpeta (la "paradoja Inception"). Si el nombre ya existe en el destino, se renombra solo: `name (1)`, `name (2)`…
- Renombrar: el nombre debe ser único entre archivos y carpetas del mismo directorio; la extensión se preserva; requiere permiso `update`.
- Búsqueda global: `LIKE` sobre `name` escapando comodines con `App\Support\QueryHelper::escapeLike` (`ESCAPE '!'`) e ignora `currentFolderId` mientras haya una búsqueda activa (commit `0cd9d7e`).

### Tareas
- Varios asignados por tarea (pivot `task_user`); el selector solo ofrece al usuario autenticado y `TaskForm` valida `assignedUserIds.*` contra `auth()->id()`.
- Estados y prioridades usan obligatoriamente los Backed Enums `App\Enums\TaskStatus` (`Todo`, `Doing`, `Done`) y `App\Enums\TaskPriority` (`Low`, `Medium`, `High`), casteados en `Task`, validados con `Rule::enum` en `TaskForm` y `ConvertInboxToTaskForm`, y verificados con `TaskStatus::tryFrom` antes de la transacción en `ReorderTasks`. El dashboard aún filtra por `'completed'` (pendiente de unificar con `TaskStatus::Done`).
- Vencimientos y fecha local: `tasks.due_date` es `dateTime` (almacenado como `'Y-m-d 00:00:00'`) y se compara siempre por fecha (`whereDate`), nunca contra `now()`. La fecha local del usuario se calcula como `now(auth()->user()->timezone ?? config('app.timezone'))->toDateString()` centralizada en `App\Support\DateHelper::userToday()` y en `$user->localDate()`. Regla de vencimiento: una tarea se considera vencida ("Overdue") si su fecha es anterior al día local del usuario (`due_date < localToday`), y "de hoy" ("Due today") si coincide con el día local del usuario (`due_date == localToday`) (commit `ba699d3`).

### Inbox
- Los ítems se convierten a otros elementos mediante modales.

### Cuenta
- Registro público desactivado (Fortify) hasta que exista el multiusuario (Fase 5); los usuarios nuevos se crean a mano.
- Avatares en S3 con URLs temporales firmadas (el bucket es privado y no se usan ACL públicas).
