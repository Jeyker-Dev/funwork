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
| Login | "Login - Refined Board Preview" | Implementado |
| Lista de proyectos | "Projects Dashboard - Luminous Editorial" | Implementado |
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

## Lista de proyectos: decisiones y ajustes para el código

Diseño aprobado: "Retomá donde estabas". Arriba, un saludo con el nombre del usuario y un resumen real ("N active projects, M tasks due this week"). Debajo, una tarjeta destacada "Continue" con el proyecto que se tocó por última vez. Después, la sección "All projects" con tarjetas compactas y un selector de vista grilla/lista.

Decisiones:

- **Sin owner en las tarjetas:** la lista solo muestra proyectos del usuario, así que el avatar y el email del owner se repetían en todas las tarjetas.
- **Datos reales:** progreso de tareas (`Done` sobre el total), próxima tarea pendiente, fecha de entrega y última edición. Se calculan sin consultas N+1.
- **Sin elementos de plantilla:** sin línea naranja superior, sin título con una frase resaltada, sin animación de las tarjetas al pasar el mouse, sin tarjeta punteada de "Create new project".

Diferencias de Stitch que se corrigen en el código:

- [x] "Open project" lleva una flecha: se quita, igual que en el login.
- [x] En la tarjeta "Continue", la barra de progreso ocupa la mitad del ancho y deja un hueco en el centro: debe ocupar el ancho del contenido hasta el botón.
- [x] La etiqueta "Progress" repite lo que ya dice "3 of 8 tasks": se quita y queda solo el conteo.
- [x] La etiqueta de tipo "Programming" usa el mismo naranja que la marca y se confunde con el acento: los colores de tipo no usan el naranja de la marca.
- [x] El saludo usa "Alex" de ejemplo: en la app muestra el nombre del usuario logueado y cambia según la hora del día.
- [x] La vista de lista no está diseñada en Stitch: tabla simple con ícono, nombre, tipo, progreso, estado y fecha de entrega.
- [x] Definir el modo oscuro y mobile, que Stitch no muestra: la tarjeta "Continue" usa `brand-tint`; en mobile, una columna y el selector de vista oculto.
- [x] Estado vacío: sin proyectos, mostrar un mensaje y una sola acción "Create your first project".

## Pendientes

- [ ] Documentar en Stitch las pantallas restantes con el mismo flujo.
