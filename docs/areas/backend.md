# Backend

Laravel 13. Skills de referencia: `laravel-clean-architecture` y `pest-laravel-testing`.

## Convenciones

- La lógica de negocio va en `app/Actions/*` y en Form Objects (`app/Livewire/Forms`).
- Autorización con Policies; ninguna acción de escritura sin policy.
- Storage siempre a través de los discos de Laravel, nunca con rutas absolutas.
- Tareas pesadas en Jobs encolados; el resultado se avisa con `DatabaseNotification`.

## Reglas de negocio vigentes

### Proyectos (`ProjectForm`)
- Requeridos: `name`, `description`, `icon`, `type`, `status`, `ownerId`.
- `dueDate` y `archiveDate` se validan como fecha; `archiveDate` se guarda en `archived_at`.

### Notebooks (`NotebookForm`)
- Requeridos: `project_id`, `created_by`. Al crear: toast, cierre del modal, reset del formulario y evento `notebook-created`.

### Archivos y carpetas
- Crear carpeta: el nombre es obligatorio y se valida.
- Borrado: prohibido si el archivo está en uso o en un proceso activo; en ese caso se devuelve un error explicativo.
- ZIP de selección: si falta algún archivo, se aborta de forma segura y se devuelve un error.
- ZIP de carpeta: asíncrono con `CreateFolderZipJob` → toast "Preparing download..." → notificación con el link (o con el fallo).
- Mover: no se permite mover al mismo directorio ni dentro de sí mismo o de una subcarpeta (la "paradoja Inception"). Si el nombre ya existe en el destino, se renombra solo: `name (1)`, `name (2)`…
- Renombrar: el nombre debe ser único entre archivos y carpetas del mismo directorio; la extensión se preserva; requiere permiso `update`.
- Búsqueda global: `LIKE` sobre `name` e ignora `currentFolderId` mientras haya una búsqueda activa.

### Tareas
- Varios asignados por tarea (pivot `task_user`). El reordenamiento está autorizado por policy.

### Inbox
- Los ítems se convierten a otros elementos mediante modales.

### Cuenta
- Avatares en S3 con URLs temporales firmadas (el bucket es privado y no se usan ACL públicas).
