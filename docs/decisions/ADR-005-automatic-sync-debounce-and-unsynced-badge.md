# ADR-005: Sincronización automática con debounce y badge de cambios pendientes en lugar de módulo de alertas

- **Fecha:** 2026-11-06
- **Estado:** Aceptada

## Contexto

Con la exportación granular a Google Docs (ADR-004), cada notebook sincronizado puede ser utilizado como fuente en NotebookLM. Sin embargo, exigir una sincronización manual tras cada edición en el editor de páginas o actualización de documentación de repositorio (`funwork:sync-docs`) genera fricción y riesgo de que NotebookLM consulte notas desactualizadas.

Por otro lado, implementar un módulo completo de alertas o notificaciones para recordar al usuario que sincronice agrega complejidad innecesaria a la arquitectura y requiere intervención humana constante.

## Decisión

1. **Sincronización automática en segundo plano:**
   - La sincronización hacia Google Docs se dispara de forma automática mediante el job encolado `SyncNotebookToGoogleDocJob`.
   - Solo aplica a notebooks que ya hayan sido sincronizados manualmente al menos una vez (`google_doc_id` no nulo). Se respeta la decisión del usuario sobre cuáles notebooks alcanzan Google y NotebookLM.
2. **Debounce de 2 minutos (`ShouldBeUnique` + `delay`):**
   - El editor guarda cambios casi en cada tipeo. El job encolado implementa `ShouldBeUnique` con `uniqueId()` = notebook ID y se encola con un delay de 2 minutos (`now()->addMinutes(2)`).
   - Múltiples modificaciones consecutivas consolidan en una sola ejecución que lee el estado final de las páginas.
3. **Badge de cambios pendientes ("Cambios sin sincronizar") en lugar de alertas:**
   - En lugar de crear un sistema complejo de notificaciones o alertas intrusivas, se añade la columna `notebooks.content_changed_at`.
   - Cuando `content_changed_at > google_doc_synced_at` (o `google_doc_synced_at` es nulo en un notebook con Doc), se muestra una pequeña etiqueta `<flux:badge color="amber" size="sm">Cambios sin sincronizar</flux:badge>` junto a la última sincronización en la tarjeta.
   - Actúa como red de seguridad visual no invasiva que desaparece automáticamente al finalizar la sincronización (automática o manual).

## Consecuencias

- Los notebooks vinculados se mantienen sincronizados con Drive sin acción manual recurrente.
- Se evitan llamadas excesivas y rate-limits a la API de Google Drive gracias al debounce de 2 minutos y deduplicación por notebook.
- No se requiere un módulo adicional de alertas ni tablas adicionales; la infraestructura existente de colas de base de datos cubre la necesidad.
