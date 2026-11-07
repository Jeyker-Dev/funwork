# Backend

Laravel 13. Skills de referencia: `laravel-conventions` y `testing-strategy`.

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
- Creación de áreas por defecto: `ProjectForm::store()` invoca `App\Actions\Projects\CreateDefaultAreasAction::execute($project)` al crear el proyecto, generando 4 áreas base: Frontend (`blue`, pos 1), Backend (`green`, pos 2), Database (`amber`, pos 3) y Design (`purple`, pos 4). Una migración de datos garantiza estas áreas en proyectos preexistentes.

### Áreas de proyecto (`Area`, `manage-areas`)
- Relación: `Project::areas()` (`hasMany`, ordenado por `position`).
- Búsqueda por clave: `Project::areaByKey(string $key): ?Area` busca por coincidencia insensible a mayúsculas sobre `slug` (`LOWER(slug) = LOWER($key)`), permitiendo resolver claves como `BACKEND`, `Backend` o `backend` (preparado para importadores de tareas y herramientas externas).
- Autorización: únicamente el dueño del proyecto puede gestionar áreas mediante `ProjectPolicy` (403 para usuarios no autorizados).
- Validación de gestión: `name` requerido (máx 50 caracteres), `slug` generado con `Str::slug($name)` con unicidad por proyecto (`Rule::unique('areas', 'slug')->where('project_id', $project->id)`), y `color` perteneciente a `Area::ALLOWED_COLORS` (`blue`, `green`, `amber`, `purple`, `red`, `cyan`, `pink`, `zinc`).
- Asignación y validación de áreas: `TaskForm`, `NotebookForm`, `ConvertInboxToTaskForm`, `ConvertInboxToNotebookForm` y el modal de cambio de área en `⚡show-notebooks` validan la pertenencia del `area_id` seleccionado con `Rule::exists('areas', 'id')->where('project_id', $project->id)`. Áreas de otros proyectos son rechazadas.
- Integridad al eliminar: al borrar un área, las tareas y notebooks asociados permanecen en el sistema con `area_id` seteado en `null` (`nullOnDelete`).

### Notebooks (`NotebookForm`)
- Requeridos: `project_id`, `created_by`. Al crear: toast, cierre del modal, reset del formulario y evento `notebook-created`.

### Exportación de notebook (`project.notebooks.export`)
- `GET /projects/{project}/notebooks/{notebook}/export` (`NotebookExportController`, `scopeBindings()`, middleware `can:view,project` y `can:view,notebook`) descarga `{notebook-slug}.md`.
- El Markdown lo arma `App\Actions\Notebooks\BuildNotebookExportAction::execute(Notebook)`. No autoriza: quien la llama verifica el acceso. Lo reutiliza la sincronización con Google Docs, para que el Doc y el `.md` tengan el mismo contenido.
- Contenido: título `# {notebook name}`, generado por FunWork el Y-m-d H:i UTC, descripción si existe, y cada página en orden (`order` luego `id`) como `## {title}` más su cuerpo. Una página sin título sale como "Página N". Si no tiene páginas, emite `_Sin páginas._`.

### Sincronización de notebook con Google Docs
- Cada usuario conecta su cuenta de Google en Settings → Integrations (`settings/integrations`). `GoogleAuthController` usa Socialite con el scope `drive.file` (solo los archivos que crea la app), `access_type=offline` y `prompt=consent` para recibir un refresh token. Si el usuario no concede Drive, la conexión se rechaza.
- `google.callback` vuelve a `session('google_return_to')` solo si es una ruta relativa del mismo sitio; si no, va a Integrations.
- `App\Services\Google\GoogleDriveClient` habla con Drive mediante el cliente `Http`, sin `google/apiclient`. Renueva el access token cuando vence o ante un 401 (un reintento). Crea el Doc subiendo Markdown con `mimeType` de Google Docs y lo actualiza con `PATCH ...?uploadType=media`, que reemplaza todo el contenido y conserva el ID.
- `App\Actions\Notebooks\SyncNotebookToGoogleDocAction` exige `NotebookPolicy::update` y arma el contenido con `BuildNotebookExportAction`. El nombre del documento creado es `"{notebook name} — {project name} (FunWork)"`. Si el Doc guardado no existe o está en la papelera, crea uno nuevo. Guarda `google_doc_id` y `google_doc_synced_at` en el notebook. Ante `GoogleAuthorizationException` (sin conexión o refresh token revocado) borra la cuenta y redirige al flujo de conexión guardando `google_return_to` apuntando al listado de notebooks (`project.notebooks`).
- **Sincronización automática con debounce (`SyncNotebookToGoogleDocJob`):**
  - **Disparadores:** eventos `saved` y `deleted` de `NotebookPage` (al crear, editar o borrar páginas) y `SyncDocsCommand` al detectar cambios (`created + updated + deleted > 0`). Registran `notebooks.content_changed_at = now()`.
  - **Regla:** solo aplica a notebooks con Google Doc existente (`google_doc_id` no nulo). Los notebooks que el usuario nunca sincronizó manualmente no se envían a Google.
  - **Debounce de 2 minutos:** el job es `ShouldQueue` y `ShouldBeUnique` (`uniqueId()` = notebook ID), despachado con `->delay(now()->addMinutes(2))`. Las ráfagas de edición quedan consolidadas en un único job.
  - **Ejecución y reintentos:** al correr recarga el notebook y omite la sync si fue eliminado, si `google_doc_id` es nulo, o si `google_doc_synced_at >= content_changed_at` (ya sincronizado). En caso contrario invoca `SyncNotebookToGoogleDocAction::execute($notebook, $owner)`. Ante `GoogleAuthorizationException` no reintenta; ante fallos de conexión o HTTP reintenta hasta 3 veces con backoff (`[10, 30, 60]`). No bloquea la cola.
  - **Badge de cambios pendientes:** en la tarjeta del notebook (`⚡show-notebooks`), si existe `google_doc_id` y `content_changed_at > google_doc_synced_at` (o `google_doc_synced_at` es null), se visualiza `<flux:badge color="amber" size="sm">` con "Cambios sin sincronizar" junto a la última sincronización, desapareciendo al sincronizar.
- Configuración: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` y `GOOGLE_REDIRECT_URI` (por defecto `/auth/google/callback`, relativo a `APP_URL`).
- Puesta en marcha en Google Cloud:
  1. Crear un proyecto y habilitar la **Google Drive API**.
  2. Configurar la pantalla de consentimiento OAuth (tipo External) con el scope `.../auth/drive.file`, que no es sensible y no requiere verificación de Google.
  3. **Publicar la app ("In production")**. En modo Testing, Google vence los refresh tokens a los 7 días.
     Para publicar, Branding pide la página principal (`https://funwork-app.duckdns.org`), la política de privacidad (`/privacy`, ruta pública `privacy`, vista `resources/views/privacy.blade.php`) y el dominio autorizado `funwork-app.duckdns.org`. `duckdns.org` está en la Public Suffix List, así que el subdominio cuenta como dominio raíz.
  4. Crear credenciales "OAuth client ID" (Web application) con la URI de redirección `https://<dominio>/auth/google/callback` (y `http://localhost:8000/auth/google/callback` para desarrollo).
  5. Cargar el client ID y el secret en el `.env` del VPS y volver a desplegar.

### Sincronización de documentación (`funwork:sync-docs`)
- Comando `funwork:sync-docs {project : ID del proyecto} {--path= : carpeta de docs, por defecto base_path('docs')}` (`App\Console\Commands\SyncDocsCommand`): espeja archivos `*.md` del repo en el notebook "Docs" del proyecto dentro de una transacción `DB::transaction` (commit `1d782a3`).
- Si el proyecto no existe: emite error y termina con código de salida 1 sin modificaciones.
- Busca o crea el notebook "Docs" asociado al proyecto con `firstOrCreate`, asignando `created_by` con el ID del dueño del proyecto (`$project->owner_id`).
- Escanea recursivamente los archivos `*.md` excluyendo `archive/`, normalizando la ruta relativa con prefijo `docs/` en `source_path` (ej. `docs/areas/backend.md`).
- Upsert por `(notebook_id, source_path)` ordenado alfabéticamente por `source_path`: `content = ['title' => source_path, 'body' => file_get_contents]`, `created_by` = dueño y `order` = posición correlativa (1-indexada).
- Borrado seguro: elimina únicamente las páginas de ese notebook cuyo `source_path` no sea nulo y cuyo archivo ya no exista en disco. Las páginas creadas a mano (`source_path` null) nunca se tocan ni se eliminan.
- Idempotencia: ejecuciones consecutivas sin cambios en disco no modifican registros y reportan 0 creadas, 0 actualizadas y 0 borradas.

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
