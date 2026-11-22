# Diseño

Herramienta: **Stitch** (proyecto `14584368472277721493`). Componentes de UI: Flux / Flux Pro.

## Design system

- Fuente de verdad visual: [`DESIGN.md`](../../DESIGN.md) ("Luminous Editorial"): paleta, tipografía, espaciados y piso de accesibilidad.
- Componentes base: Flux / Flux Pro (no recrear lo que ya ofrece Flux).
- Estilo del dashboard: layout tipo bento.
- Modo claro/oscuro: configurable desde Settings → Appearance.

## Flujo de rediseño

1. Se prototipa la pantalla en Stitch y se revisa ahí antes de escribir código.
2. Cuando Stitch llega a un resultado cercano y deja de mejorar con prompts, no se sigue iterando en Stitch: las diferencias se anotan en esta página y se resuelven en el código.
3. El sistema es la versión final; Stitch es la referencia.

## Pantallas en Stitch

| Pantalla | Pantalla de Stitch | Estado |
|---|---|---|
| Login | "Login - Refined Board Preview" | Implementado en `feat/login-redesign` |
| Lista de proyectos | | |
| Dashboard del proyecto | | |
| Tablero de tareas | | |
| Inbox | | |
| Notebooks / editor | | |
| Archivos | | |

## Login: decisiones y ajustes para el código

Diseño aprobado: layout dividido. A la izquierda, el formulario alineado a la izquierda, sin tarjeta, con el título "Pick up where you left off.". A la derecha, una ventana con un tablero de tareas de ejemplo ("Website relaunch").

Decisiones:

- **Botón principal:** degradado de `#ff6d00` a `#ff8a3d` con texto `#431407` en semibold (contraste 5.5:1). El naranja claro con texto blanco falla el contraste mínimo (2.3–2.8:1). Al pasar el mouse: de `#f45f00` a `#ff7a24`.
- **Sin elementos de plantilla:** sin puntos de semáforo, sin etiquetas en mayúsculas, sin flecha en el botón, sin línea naranja superior.
- **Sin textos falsos:** nada de "Realtime sync", región, "30 days", footer legal ni "Create account" mientras el registro esté desactivado en Fortify.

Diferencias de Stitch que se corrigen en el código:

- [x] "Ready for QA" e "In progress" se parten en dos líneas: deben ocupar una sola línea.
- [x] La tarjeta "Review the login redesign" está en Done pero dice "Ready for QA": en Done debe decir "Completed", igual que la otra tarjeta de esa columna.
- [x] Los avatares del encabezado ("AL", "SK") quedan recortados por la superposición: deben leerse completos.
- [x] El logo en Stitch es un placeholder: usar el logo real de FunWork, también en modo oscuro.
- [x] Definir el modo oscuro, que Stitch no tiene: tarjetas con `surface-high` y borde ghost.
- [x] En mobile se oculta el tablero y queda solo el formulario.

## Pendientes

- [ ] Documentar en Stitch las pantallas restantes con el mismo flujo.
