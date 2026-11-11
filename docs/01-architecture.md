# Arquitectura

## Stack

- **Backend:** Laravel 13, PHP 8.4.1+, Fortify (auth y 2FA), `laravel/mcp` (servidor MCP).
- **UI:** Livewire v4 (Single File Components nativos, sin Volt), Flux / Flux Pro, Alpine v3, TailwindCSS, Vite.
- **Storage:** discos de Laravel; S3 para avatares y archivos de proyecto (URLs temporales firmadas para buckets privados).
- **Colas:** jobs en base de datos (por ejemplo `CreateFolderZipJob`).
- **Notificaciones:** `DatabaseNotification` (por ejemplo, el link de descarga del ZIP).
- **Testing:** Pest v4 + pest-plugin-laravel. **Lint:** Laravel Pint.
- **CI:** GitHub Actions (`lint.yml`, `tests.yml`).
- **CD:** `deploy.yml` construye la imagen y despliega al VPS en cada push a `master`. `sync-docs.yml` espeja `docs/` en el notebook "Docs" del proyecto FunWork en cada push a `develop` o `master` que toca `docs/**`: copia `docs/` al contenedor `app` y corre `funwork:sync-docs`. Requiere los secrets `VPS_HOST`, `VPS_USERNAME`, `VPS_SSH_KEY` y `FUNWORK_DOCS_PROJECT_ID` (ID del proyecto FunWork en producción). Ambos workflows comparten el grupo de concurrencia `deploy-production`, así una sincronización nunca corre mientras se recrea el contenedor.
- **Deploy manual (`scripts/deploy.sh`):** alternativa a Actions mientras la cuenta de GitHub está bloqueada por facturación (2026-11-05). Corre Pest y Pint en local y después, por SSH en `~/funwork` (`set -euo pipefail`), ejecuta el siguiente orden para abortar ante cualquier fallo antes de afectar los contenedores en ejecución:
  1. Actualiza el checkout `src/` (`git reset --hard origin/$BRANCH`), construye la imagen `funwork:<sha>` y exporta `APP_IMAGE="funwork:<sha>"`.
  2. **Preflight de base de datos:** levanta el servicio MySQL (`docker compose -f compose.prod.yml up -d mysql`) y sondea con `mysqladmin ping` (~30 reintentos cada 2s); si no responde en 60s, aborta.
  3. **Backup previo a migrar:** crea un dump comprimido en `~/funwork/backups/backup_YYYYMMDD_HHMMSS_<sha>.sql.gz` ejecutando `mysqldump` dentro del contenedor MySQL hacia un archivo temporal `.tmp`. Renombra a su nombre final solo en éxito (en fallo elimina el `.tmp` y aborta) y retiene únicamente los 10 archivos más recientes coincidentes con `backup_*.sql.gz`.
  4. **Migración aislada:** ejecuta las migraciones en un contenedor descartable (`docker compose -f compose.prod.yml run --rm --no-deps -T app php artisan migrate --force </dev/null`) con la nueva imagen.
  5. **Despliegue de la nueva versión:** recién tras migrar exitosamente, reinicia los servicios con `docker compose -f compose.prod.yml up -d --remove-orphans`.
  6. **Sincronización y poda:** espeja `docs/` en el notebook "Docs" con `funwork:sync-docs` y poda imágenes viejas dejando la activa, `latest` y la imagen previa de la app para que el rollback funcione (una única versión previa).

  Se configura con `FUNWORK_DEPLOY_HOST`, `FUNWORK_DOCS_PROJECT_ID` y `FUNWORK_DEPLOY_BRANCH` (por defecto `master`), en el entorno o en un `.deploy.env` (`.gitignore`). En el VPS necesita git, Docker, `~/funwork/.env`, `~/funwork/auth.json` (credenciales de Flux Pro) y el directorio `~/funwork/backups`. `compose.prod.yml` toma por defecto la imagen local `funwork:latest`.

  Restauración manual de base de datos:
  ```bash
  gunzip < ~/funwork/backups/<file>.sql.gz | docker compose -f compose.prod.yml exec -T mysql sh -c 'mysql -u root -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
  ```

  Rollback de código (se conserva una única versión previa en el VPS):
  ```bash
  APP_IMAGE=funwork:<previous-sha> docker compose -f compose.prod.yml up -d
  ```
- **Backups:** timer diario de systemd `funwork-backup.timer` (~03:05 hora del servidor) que ejecuta `/usr/local/bin/funwork-backup` para respaldar MySQL + `.env` en un bucket S3 dedicado mediante restic (cifrado; requiere la contraseña del repositorio). Retención: `restic forget --prune --keep-daily 7 --keep-weekly 4 --keep-monthly 6`. Complementa los dumps locales previos a cada migración en `~/funwork/backups` (los 10 más recientes).

  Restauración desde restic:
  ```bash
  restic snapshots
  restic restore <id> --target <dir>
  ```

## Capas y responsabilidades

| Capa | Ubicación | Responsabilidad |
|---|---|---|
| Rutas | `routes/web.php`, `routes/settings.php`, `routes/ai.php` | Rutas full-page con `Route::livewire` bajo `auth` y endpoint MCP HTTP (`/mcp/funwork`) con `AuthenticateMcpWithApiToken`. |
| MCP | `app/Mcp/Servers`, `app/Mcp/Tools` | Servidor MCP (`FunWorkServer`) y herramientas para agentes externos (Antigravity). |
| Páginas | `resources/views/pages/**/⚡*.blade.php` | Componentes SFC que orquestan cada vista. |
| Componentes | `resources/views/components/projects/**` | UI reutilizable por módulo. |
| Form Objects | `app/Livewire/Forms` | Validación y persistencia de formularios. |
| Actions | `app/Actions` (`Auth`, `Files`, `Fortify`, `Notebooks`, `Projects`, `Tasks`) | Lógica de negocio reutilizable. |
| Jobs / Notifications | `app/Jobs`, `app/Notifications` | Trabajo asíncrono y avisos al usuario. |
| Modelos | `app/Models` | `Project`, `Area`, `Task`, `InboxItem`, `Notebook`, `NotebookPage`, `Folder`, `ProjectFile`, `GoogleAccount`, `ApiToken`, `User`. |

## Convenciones críticas

- Los componentes Livewire se crean **siempre** con `php artisan make:livewire`, nunca a mano.
- No usar `Livewire\Volt\Volt`; en tests usar `Livewire::test()` / `Livewire::actingAs()`.
- La lógica de negocio va en Actions o Form Objects, no en las vistas (skill `laravel-conventions`).
- Autorización: todas las rutas `/projects/{project}/*`, componentes Livewire, Form Objects, Actions y herramientas MCP verifican propiedad mediante Policies en `app/Policies` (`ProjectPolicy`, `TaskPolicy`, `NotebookPolicy`, `NotebookPagePolicy`, `FolderPolicy`, `ProjectFilePolicy`, `InboxItemPolicy`).

## Mapa de rutas

| Ruta | Destino |
|---|---|
| `/projects` | `pages::projects.index` |
| `/projects/{project}` | `pages::projects.show` (dashboard) |
| `/projects/{project}/inbox` | `pages::projects.inbox` |
| `/projects/{project}/tasks[/{task}]` | tablero y detalle de tareas (`pages::projects.tasks.*`) |
| `/projects/{project}/notebooks` | `pages::projects.notebooks.index` |
| `/projects/{project}/notebooks/{notebook}/pages/{page}/edit` | editor de páginas |
| `/projects/{project}/files` | `pages::projects.files.index` |
| `/projects/{project}/files/{projectFile}/preview` | `ProjectFilePreviewController` |
| `POST /mcp/funwork` | `FunWorkServer` (autenticado con `Bearer` token vía `AuthenticateMcpWithApiToken`) |

## Decisiones

Las decisiones de arquitectura se registran en [`decisions/`](decisions/).
