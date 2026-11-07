# Frontend

Livewire v4 SFC + Flux / Flux Pro + Alpine v3 + Tailwind. Skill de referencia: `laravel-conventions` (`references/livewire.md`).

## Convenciones

- Páginas en `resources/views/pages/**/⚡*.blade.php`; componentes por módulo en `resources/views/components/projects/**`.
- Crear componentes con `php artisan make:livewire`.
- Feedback con toasts de Flux; los modales se abren y cierran desde el componente.
- Comunicación entre componentes por eventos (`project-created`, `notebook-created`, `search-updated`).
- Colores y clases CSS por estado de tarea: se consumen exclusivamente a través de los métodos del enum `app/Enums/TaskStatus.php` (`dot()`, `columnBorder()`, `columnBg()`, `badge()`, `cardClasses()`, `text()`, `accent()`, `fluxColor()`). En `resources/css/app.css` se mantiene `@source '../../app/Enums';` para el escaneo de Tailwind CSS v4.

## Estado actual por módulo

### Proyectos
- El índice muestra un estado vacío con el CTA `create-project`, o la lista `show-projects`.
- `create-project` cambia su trigger según `$elementName` (`header` o la card de estado vacío).
- `show-projects` filtra por `$search` y reacciona a `search-updated` y a `project-created`.
- **Gestión de áreas (`⚡manage-areas`):** accesible desde el header del proyecto mediante un modal Flux (`manage-areas-modal`). Permite al dueño crear, renombrar, cambiar color (paleta fija de Flux), reordenar posiciones (subir/bajar) y eliminar áreas con modal de confirmación (`confirm-delete-area`).

### Dashboard (`projects.show`)
- Layout tipo bento con captura rápida, estadísticas, tareas urgentes y actividad reciente.

### Tareas
- Tablero por estados con drag & drop entre columnas, contador por columna, slider con filtros, paginación e indicador "ver más".
- **Filtro por área en el tablero (`⚡task-board`):** propiedad sincronizada en la URL (`#[Url] $area = 'all'`) que ofrece opciones "All", cada área del proyecto en orden y "No area". La selección filtra las tarjetas y sincroniza automáticamente los contadores (`⚡task-count`, `⚡task-more-indicator`), la lista (`⚡show-tasks`) y el slider de tareas ocultas (`⚡slider-filters`, `⚡slider-tasks`, `⚡slider-pagination`) mediante el prop `:area`.
- **Badges de área:** renderizado como `<flux:badge size="sm" :color="$area->color">` en las tarjetas de tareas (`card.blade.php`), en el flyout lateral (`⚡slider-tasks`), en el listado (`⚡show-tasks`) y en la página de detalle de tarea (`pages/projects/tasks/⚡show.blade.php`).
- **Eager loading sin N+1:** se incluye `Task::with('area')` en el tablero, slider y vistas de tareas, preservando intacto el presupuesto fijo de queries validado en `TaskBoardQueryCountTest`.
- Vistas full-page registradas bajo componentes Livewire `pages::projects.tasks.*` (`pages/projects/tasks/⚡index.blade.php` para `'project.tasks'` y `pages/projects/tasks/⚡show.blade.php` para `'show.task'`) usando el layout `layouts.app` con `:project`.
- Página de detalle de tarea (`pages::projects.tasks.show`): visualización con insignias consumiendo los métodos del enum `TaskStatus`, badge de área, prioridad, vencimiento y asignados; formulario de edición con selector de área persistido mediante `TaskForm::update` con botón explícito "Save changes" y borrado con modal de confirmación Flux y redirección a `project.tasks`.
- Historial: bugs de duplicación y de contadores al arrastrar entre columnas (ya corregidos). Es la zona más frágil: cualquier cambio necesita tests.

### Notebooks
- Estado vacío: "No Notebooks, Yet.". Lista con filtros. Editor de páginas.
- **Insignia y cambio de área:** badge `<flux:badge size="sm" :color="$notebook->area->color">` en la tarjeta de notebook (`⚡show-notebooks`), selector de área en creación (`⚡create-notebook`), y modal para "Change area" desde el menú contextual de cada notebook.

### Archivos
- Grid de carpetas y archivos con **menú contextual por ítem** (Download, Move, Delete, Copy Link). Una acción individual reemplaza la selección múltiple por `[$id]`.
- **Lightbox:** imágenes y SVG seguros de menos de 15 MB; PDF nativo solo en desktop; en mobile, archivos de más de 15 MB o tipos no soportados muestran un fallback de descarga. Navegación anterior/siguiente en el cliente con Alpine, focus trap y cierre con ESC.
- **Búsqueda global** con `wire:model.live.debounce`; durante la búsqueda las cards muestran un breadcrumb truncado con tooltip; al limpiarla se vuelve a la carpeta original.
- **Renombrar:** el input aparece enfocado y la extensión se muestra como sufijo no editable.

## Deuda técnica / pendientes

- [x] Migrar `/projects/{project}/tasks` y `/projects/{project}/tasks/{task}` a `Route::livewire` bajo `pages::projects.tasks.*`.
- [x] Borrar tarea desde la página de detalle (modal de confirmación, `TaskPolicy::delete`, redirección al tablero).
