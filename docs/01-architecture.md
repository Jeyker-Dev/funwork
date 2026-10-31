# Arquitectura

## Stack

- **Backend:** Laravel 13, PHP 8.3+, Fortify (auth y 2FA).
- **UI:** Livewire v4 (Single File Components nativos, sin Volt), Flux / Flux Pro, Alpine v3, TailwindCSS, Vite.
- **Storage:** discos de Laravel; S3 para avatares y archivos de proyecto (URLs temporales firmadas para buckets privados).
- **Colas:** jobs en base de datos (por ejemplo `CreateFolderZipJob`).
- **Notificaciones:** `DatabaseNotification` (por ejemplo, el link de descarga del ZIP).
- **Testing:** Pest v4 + pest-plugin-laravel. **Lint:** Laravel Pint.
- **CI:** GitHub Actions (`lint.yml`, `tests.yml`).

## Capas y responsabilidades

| Capa | Ubicación | Responsabilidad |
|---|---|---|
| Rutas | `routes/web.php` | Rutas full-page con `Route::livewire`, bajo el middleware `auth`. |
| Páginas | `resources/views/pages/**/⚡*.blade.php` | Componentes SFC que orquestan cada vista. |
| Componentes | `resources/views/components/projects/**` | UI reutilizable por módulo. |
| Form Objects | `app/Livewire/Forms` | Validación y persistencia de formularios. |
| Actions | `app/Actions` (`Files`, `Fortify`) | Lógica de negocio reutilizable. |
| Jobs / Notifications | `app/Jobs`, `app/Notifications` | Trabajo asíncrono y avisos al usuario. |
| Modelos | `app/Models` | `Project`, `Task`, `InboxItem`, `Notebook`, `NotebookPage`, `Folder`, `ProjectFile`, `User`. |

## Convenciones críticas

- Los componentes Livewire se crean **siempre** con `php artisan make:livewire`, nunca a mano.
- No usar `Livewire\Volt\Volt`; en tests usar `Livewire::test()` / `Livewire::actingAs()`.
- La lógica de negocio va en Actions o Form Objects, no en las vistas (skill `laravel-clean-architecture`).
- Autorización con Policies (por ejemplo, el reordenamiento de tareas y el renombrado de archivos).

## Mapa de rutas

| Ruta | Destino |
|---|---|
| `/projects` | `pages::projects.index` |
| `/projects/{project}` | `pages::projects.show` (dashboard) |
| `/projects/{project}/inbox` | `pages::projects.inbox` |
| `/projects/{project}/tasks[/{task}]` | tablero de tareas (ruta `get` clásica) |
| `/projects/{project}/notebooks` | `pages::projects.notebooks.index` |
| `/projects/{project}/notebooks/{notebook}/pages/{page}/edit` | editor de páginas |
| `/projects/{project}/files` | `pages::projects.files.index` |
| `/projects/{project}/files/{projectFile}/preview` | `ProjectFilePreviewController` |

## Decisiones

Las decisiones de arquitectura se registran en [`decisions/`](decisions/).
