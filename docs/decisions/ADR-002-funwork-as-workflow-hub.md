# ADR-002: FunWork como hub del flujo NotebookLM / Antigravity / Stitch

- **Fecha:** 2026-10-31
- **Estado:** Aceptada

## Contexto

El flujo de trabajo usa NotebookLM (análisis), Antigravity (ejecución) y Stitch (diseño), con `docs/` como fuente de verdad. La meta es que FunWork centralice ese flujo y, a futuro, tenga un modelo de IA por módulo.

Restricciones verificadas el 2026-10-31:

- Antigravity admite servidores MCP remotos (`~/.gemini/config/mcp_config.json`).
- NotebookLM no tiene API para cuentas personales; solo existe la API de Enterprise, en alpha.
- Stitch exporta un `DESIGN.md`. Solo hay servidores MCP de la comunidad.

## Decisión

1. **Las tareas viven en FunWork** (backlog, sprint, estado). `docs/tasks/*.md` es transitorio y se retira cuando exista el MCP.
2. **El conocimiento versionado vive en el repo** (`docs/`: visión, arquitectura, áreas, ADRs). FunWork lo enlaza; no lo duplica.
3. **NotebookLM se integra con archivos:** un importador de tareas (formato fijo pegado en FunWork) y un export Markdown del proyecto ("Project pack").
4. **Antigravity se integra por MCP:** FunWork expone herramientas para leer y actualizar tareas, inbox, notebooks y decisiones.
5. **Cada módulo produce datos estructurados exportables a Markdown**, como base para los futuros modelos por módulo.

## Consecuencias

- Hace falta autorización real (Policies) y API tokens antes de exponer el MCP.
- Las áreas pasan a ser un dato de dominio (no solo archivos en `docs/areas/`).
- Mientras NotebookLM no tenga API, mover información hacia y desde NotebookLM sigue siendo un paso manual, aunque mínimo.
