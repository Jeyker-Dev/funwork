# ADR-001: Documentación en dos capas y flujo ODD

- **Fecha:** 2026-10-30
- **Estado:** Aceptada

## Contexto

El proyecto se documentaba con OpenSpec/SDD (`openspec/`, `sdd/`): proposal → spec → design → tasks → apply → verify → archive. gentle-ai reemplazó SDD por ODD (Organic Driven Development). Además, el contexto del proyecto vivía en sesiones de chat sueltas, una por área, en lugar de en archivos.

## Decisión

1. `docs/` es la capa de **proyecto**: visión, arquitectura, áreas, ADRs, backlog y changelog. NotebookLM la usa como fuente.
2. `odd/tasks/<feature>.md` es la capa de **feature**, gestionada por el agente según ODD.
3. `openspec/` y `sdd/` se mueven a `docs/archive/` como historial de solo lectura. Las specs vigentes se volcaron en `docs/areas/`.

## Consecuencias

- Hay una sola fuente de verdad por nivel y no hay flujos SDD activos.
- Después de cada ciclo hay que volver a subir a NotebookLM los archivos de `docs/` que cambiaron.
- Los agentes leen `AGENTS.md` → `docs/` antes de implementar.
