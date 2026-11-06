# ADR-004: Exportación y sincronización con Google Docs por notebook en lugar de Project pack

- **Fecha:** 2026-11-06
- **Estado:** Aceptada

## Contexto

El Project pack inicial (ADR-003) exportaba todas las tareas y todos los notebooks no archivados de un proyecto en un solo archivo Markdown / Google Doc. Esto sobrecargaba NotebookLM con notas o tareas irrelevantes. El usuario necesita elegir de forma granular qué notas van a NotebookLM, notebook por notebook.

## Decisión

1. **Reemplazo del Project pack:** Se elimina la exportación global del proyecto (`project.pack`) y la sincronización a nivel de proyecto (`projects.google_doc_id`). La exportación de tareas queda fuera de alcance por el momento.
2. **Exportación por notebook:** Cada tarjeta de notebook en el listado permite descargar su contenido como Markdown (`GET /projects/{project}/notebooks/{notebook}/export`, `project.notebooks.export`).
3. **Un Google Doc por notebook:** Se migra el almacenamiento del Doc a la tabla `notebooks` (`google_doc_id` y `google_doc_synced_at`). Cada notebook se sincroniza de forma independiente con Google Docs manteniendo el ID del archivo (`"{notebook name} — {project name} (FunWork)"`).
4. **Reutilización de OAuth y Drive client:** Se mantiene intacta la integración de Google OAuth por usuario (Socialite + scope `drive.file`) y el cliente `GoogleDriveClient`.

## Consecuencias

- En NotebookLM, el usuario puede vincular notebooks individuales como fuentes separadas y actualizarlas con "Sync".
- Se evita sobrecargar NotebookLM con contexto no deseado.
- La interfaz del dashboard del proyecto se simplifica al trasladar las acciones al menú de cada tarjeta de notebook.
