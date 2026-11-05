# Base de datos

## Tablas de dominio

| Tabla | Notas |
|---|---|
| `users` | Incluye columnas de 2FA, `username`, `profile_photo_path` y `timezone`. |
| `projects` | Usa `archived_at` (no `archive_at`). |
| `tasks` | `due_date` es una columna `date`. |
| `task_user` | Pivot para varios asignados por tarea. |
| `inbox_items` | Capturas rápidas por proyecto. |
| `notebooks` / `notebook_pages` | Un notebook tiene muchas páginas; pertenece a un proyecto y a su creador. `notebook_pages` cuenta con la columna nullable `source_path` y un índice único compuesto `(notebook_id, source_path)` para el espejado de docs vía `funwork:sync-docs` (commit `1d782a3`). |
| `folders` / `project_files` | Jerarquía de carpetas y archivos por proyecto. |
| `notifications` | Notificaciones en base de datos (por ejemplo, la descarga de ZIP). |
| `jobs` / `cache` | Infraestructura de Laravel. |

## Reglas

- Todo cambio de esquema se hace con una migración nueva; nunca se editan migraciones que ya se aplicaron.
- Toda tabla nueva necesita factory, para poder usarla en los tests de Pest.

## Riesgos conocidos

- `due_date` es `date` y la app corre en UTC: una tarea puede mostrarse "vencida" o "de hoy" incorrectamente según la zona horaria del usuario. Ya existe `users.timezone`; falta usarla de forma consistente en las consultas de urgencia.
- La actividad reciente del dashboard usa `UNION ALL` entre `notebooks` y `project_files` (columnas `id`, `name`, `project_id`, `updated_at`). Si se agregan tipos nuevos, revisar esta consulta.
