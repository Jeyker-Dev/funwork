# Documentación de FunWork

Esta carpeta es la **fuente de verdad del proyecto**. Lo que no está escrito aquí no existe.

| Capa | Ubicación | Para qué |
|---|---|---|
| Proyecto | `docs/` | Visión, arquitectura, áreas. Fuente de NotebookLM. |
| Decisiones | FunWork (módulo Decisions) | ADRs del proyecto (barra lateral → Decisions; `list_decisions` / `log_decision` vía MCP). |
| Feature | `odd/tasks/<feature>.md` | Documento ODD de cada feature en curso: specs, tareas y log. Lo crea y mantiene el agente (Antigravity/Claude). |
| Historial | `docs/archive/` | Material anterior (OpenSpec / SDD y ADRs previos en `archive/decisions/`). Solo lectura. |

El backlog, el sprint actual y las decisiones (ADRs) se gestionan en la aplicación FunWork (proyecto "FunWork"). Los agentes leen y crean tareas y ADRs a través del servidor MCP de FunWork.

## Mapa

- [`00-vision.md`](00-vision.md): qué es FunWork, para quién y qué entra en el alcance.
- [`01-architecture.md`](01-architecture.md): stack, capas, módulos y convenciones.
- [`roadmap.md`](roadmap.md): fases, prioridades y por qué (auditoría del 2026-10-31).
- [`areas/`](areas/): estado actual y reglas por área (frontend, backend, database, design).
- **Decisiones (ADRs):** módulo **Decisions** en FunWork (barra lateral del proyecto → Decisions; `list_decisions` / `log_decision` vía MCP). Historial archivado en [`archive/decisions/`](archive/decisions/).
- [`changelog.md`](changelog.md): qué se hizo y cuándo.

## Ciclo de trabajo

1. **Planificar (NotebookLM):** analizar `docs/` y generar tareas candidatas.
2. **Diseñar (Stitch):** si hay UI, actualizar `areas/design.md`.
3. **Debatir y ejecutar (Antigravity, ODD):** el backlog y el sprint actual se gestionan en la aplicación FunWork (proyecto "FunWork"); los agentes leen y actualizan tareas a través del servidor MCP de FunWork. Si la tarea es grande, el agente abre `odd/tasks/<feature>.md`.
4. **Cerrar:** marcar la tarea en FunWork vía MCP (`update_task_status`), actualizar `changelog.md` y, si hubo una decisión de arquitectura, registrarla en el módulo Decisions vía MCP (`log_decision`). Volver a subir a NotebookLM los archivos que cambiaron.

Formato de tarea:

```
- [ ] [AREA] Título — criterio de aceptación — depende de: X
```
