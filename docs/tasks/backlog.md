# Backlog

Formato: `- [ ] [AREA] Título — criterio de aceptación — depende de: X`

## Pendientes

- [ ] [DOCS] Completar las secciones "(por confirmar)" de `00-vision.md` — problema, usuario y objetivos definidos — depende de: -
- [ ] [DESIGN] Vincular las pantallas de Stitch en `areas/design.md` — cada pantalla con link o export — depende de: -
- [ ] [BACKEND] Usar `users.timezone` en las consultas de tareas urgentes/vencidas — una tarea no aparece vencida antes de tiempo en zonas UTC-x — depende de: -
- [ ] [FRONTEND] Evaluar migrar `/projects/{project}/tasks` a `Route::livewire` — la ruta es consistente con el resto de los módulos — depende de: -
- [ ] [QA] Verificar la cobertura de tests de búsqueda global y del dashboard (changes que se cerraron sin verify-report) — tests de Pest en verde para ambos — depende de: -

## Migrado desde OpenSpec

- `search-files-folders`: implementado (commit `0950bf6`), sin verify-report → ver la tarea de QA.
- `home-dashboard`: implementado (commits `31ebbd7`, `21f2c37`, `45fc729`), sin verify-report → ver la tarea de QA.
- `projects-tests`: completado y verificado (2026-07-10).
- `individual-context-menu`: archivado (2026-07-22).
