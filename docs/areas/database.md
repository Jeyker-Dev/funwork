# Base de datos

## Tablas de dominio

| Tabla | Notas |
|---|---|
| `users` | Incluye columnas de 2FA, `username`, `profile_photo_path` y `timezone`. |
| `projects` | Usa `archived_at` (no `archive_at`). |
| `api_tokens` | Tokens personales de API para el servidor MCP (`user_id`, `name`, `token` único de 64 caracteres con hash SHA-256, `last_used_at`, `expires_at`). El token en texto plano (`fw_...`) nunca se persiste. |
| `google_accounts` | Una cuenta de Google por usuario (`user_id` único). `access_token` y `refresh_token` se guardan con el cast `encrypted` (dependen de `APP_KEY`). |
| `tasks` | `due_date` es una columna `date`. Clave foránea nullable `area_id` (`nullOnDelete`) para asociar un área del proyecto. |
| `task_user` | Pivot para varios asignados por tarea. |
| `areas` | Áreas configurables por proyecto (`project_id`, `name`, `slug`, `color`, `position`). Unicidad compuesta `(project_id, slug)`. Colores restringidos a una lista fija de Flux badges (`blue`, `green`, `amber`, `purple`, `red`, `cyan`, `pink`, `zinc`). |
| `inbox_items` | Capturas rápidas por proyecto. |
| `notebooks` / `notebook_pages` | Un notebook tiene muchas páginas; pertenece a un proyecto y a su creador. Guarda clave foránea nullable `area_id` (`nullOnDelete`) a nivel notebook completo (no por página). Guarda además `google_doc_id`, `google_doc_synced_at` y `content_changed_at` (nullable) para la sincronización con Google Docs. `notebook_pages` cuenta con la columna nullable `source_path` y un índice único compuesto `(notebook_id, source_path)` para el espejado de docs vía `funwork:sync-docs` (commit `1d782a3`). |
| `folders` / `project_files` | Jerarquía de carpetas y archivos por proyecto. |
| `notifications` | Notificaciones en base de datos (por ejemplo, la descarga de ZIP). |
| `jobs` / `cache` | Infraestructura de Laravel. |

## Reglas

- Todo cambio de esquema se hace con una migración nueva; nunca se editan migraciones que ya se aplicaron.
- Toda tabla nueva necesita factory, para poder usarla en los tests de Pest.

## Riesgos conocidos

- `due_date` es `date` y la app corre en UTC: una tarea puede mostrarse "vencida" o "de hoy" incorrectamente según la zona horaria del usuario. Ya existe `users.timezone`; falta usarla de forma consistente en las consultas de urgencia.
- La actividad reciente del dashboard usa `UNION ALL` entre `notebooks` y `project_files` (columnas `id`, `name`, `project_id`, `updated_at`). Si se agregan tipos nuevos, revisar esta consulta.
