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
| Home del proyecto | "Home - Website Relaunch" | Diseño aprobado; pendiente de implementar |
| Tablero de tareas | | |
| Inbox | "Inbox - Website Relaunch" y "Inbox - Process Mode Focus Panel" | Diseño aprobado; pendiente de implementar |
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

## Home del proyecto: decisiones y ajustes para el código

Diseño aprobado: "Estado del proyecto + qué hago hoy". Arriba, el encabezado del proyecto con progreso y conteo por estado. Debajo, la captura rápida ancha. Después, dos columnas: "Today" a la izquierda; "Areas" y "Recent activity" a la derecha. Toda la pantalla va en inglés (ver `DESIGN.md`, sección 9).

Decisiones:

- **Sin saludo ni tarjetas de métricas:** el saludo vive en la lista de proyectos.
- **"Today" honesto:** muestra tareas vencidas y de hoy, que se marcan como hechas con un clic. Si no hay ninguna, lo dice y muestra "Up next". Se elimina el comportamiento de `DashboardStatsData::highlightedTasksCount()`, que mostraba todas las tareas abiertas bajo la etiqueta "para hoy".
- **Áreas con progreso:** cada área muestra sus tareas `Done` sobre el total.
- **Actividad reciente mezclada:** notebooks, decisiones, archivos y tareas completadas, con tiempo relativo.

Diferencias de Stitch que se corrigen en el código:

- [ ] El encabezado tiene una línea naranja superior: se quita (es un elemento de plantilla).
- [ ] El progreso aparece tres veces (anillo de 60 %, "12 of 20 tasks done" y la barra): queda solo la barra con "12 of 20 tasks done".
- [ ] "8 tasks remaining in active sprint": FunWork no tiene sprints; se quita.
- [ ] La tarjeta "Design guidelines" con foto no existe en FunWork: se quita.
- [ ] "FunWork Studio" al pie del sidebar: se quita, salvo lo que el sidebar ya tiene hoy (Change Log).
- [ ] El ícono del selector de proyecto (cohete) no coincide con el del encabezado: se usa el ícono real del proyecto en los dos lugares.
- [ ] "View all activity" no tiene una página destino: se quita, salvo que exista una.
- [ ] Definir el modo oscuro, mobile (una columna: Today, Areas, Recent activity) y los estados vacíos de cada tarjeta.

## Inbox: decisiones y ajustes para el código

Diseño aprobado: "Capturá en un segundo, procesá en un minuto". Arriba, el título con el contador "N to process" y el botón "Process inbox". Debajo, una barra de captura que guarda con Enter. Después, los ítems agrupados por antigüedad (Today, This week, Older) en filas simples. El modo "Process" muestra un ítem por vez en un panel centrado.

Decisiones:

- **Captura con Enter:** sin botón "Add to Inbox" separado; un aviso de tecla Enter dentro de la barra.
- **Acciones por fila:** "Make task", "Make note" y un ícono de borrar aparecen al pasar el mouse o al enfocar la fila con el teclado; en mobile se ven siempre. Borrar no usa rojo.
- **Modo "Process":** contador y barra ("2 of 4"), el texto del ítem grande, "Captured N ago", tres acciones con atajos de teclado (T, N, D), "Skip" y cerrar. "Make task" y "Make note" reutilizan los modales de convertir que ya existen. Al terminar el último ítem, muestra el estado vacío.
- **Estado vacío "Inbox zero":** un mensaje que explica para qué sirve el Inbox y el foco puesto en la barra de captura.
- **Datos:** no se agregan columnas; los grupos salen de `created_at`.

Diferencias de Stitch que se corrigen en el código:

- [ ] En el sidebar, "Inbox" no aparece como ítem activo en ninguna de las dos pantallas: debe usar el resaltado activo.
- [ ] "FunWork Studio" al pie del sidebar y el cohete del selector de proyecto: igual que en el Home, se quitan o se usa el ícono real.
- [ ] Los atajos T, N y D deben funcionar solo dentro del modo "Process", con Escape para cerrar, y nunca mientras se escribe en un campo.
- [ ] Definir el modo oscuro, mobile (el panel de "Process" ocupa casi toda la pantalla y los botones se apilan) y el estado vacío.

## Pendientes

- [ ] Documentar en Stitch las pantallas restantes con el mismo flujo.
