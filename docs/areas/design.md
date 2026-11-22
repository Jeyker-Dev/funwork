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
| Home del proyecto | "Home - Website Relaunch" | Implementado |
| Tablero de tareas | "Tasks - Website Relaunch" y "Tasks - Task Detail Side Panel" | Implementado |
| Inbox | "Inbox - Website Relaunch" y "Inbox - Process Mode Focus Panel" | Implementado |
| Notebooks / editor | "Notebooks - Website Relaunch" y "Notebook Detail - Architecture" | Diseño aprobado; pendiente de implementar |
| Decisions | "Decisions - Website Relaunch" y "Decision Detail - Livewire Single-File Components" | Diseño aprobado; pendiente de implementar |
| Archivos | "Files - Website Relaunch" y "Files - Drag and Drop Upload State" | Diseño aprobado; pendiente de implementar |
| Settings de la cuenta | "Account Settings - FunWork" y "Account Settings - Integrations" | Diseño aprobado; pendiente de implementar |
| Settings del proyecto (nueva) | "Project Settings - Website Relaunch" | Diseño aprobado; pendiente de implementar |

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

- [x] El encabezado tiene una línea naranja superior: se quita (es un elemento de plantilla).
- [x] El progreso aparece tres veces (anillo de 60 %, "12 of 20 tasks done" y la barra): queda solo la barra con "12 of 20 tasks done".
- [x] "8 tasks remaining in active sprint": FunWork no tiene sprints; se quita.
- [x] La tarjeta "Design guidelines" con foto no existe en FunWork: se quita.
- [x] "FunWork Studio" al pie del sidebar: se quita, salvo lo que el sidebar ya tiene hoy (Change Log).
- [x] El ícono del selector de proyecto (cohete) no coincide con el del encabezado: se usa el ícono real del proyecto en los dos lugares.
- [x] "View all activity" no tiene una página destino: se quita, salvo que exista una.
- [x] Definir el modo oscuro, mobile (una columna: Today, Areas, Recent activity) y los estados vacíos de cada tarjeta.

## Inbox: decisiones y ajustes para el código

Diseño aprobado: "Capturá en un segundo, procesá en un minuto". Arriba, el título con el contador "N to process" y el botón "Process inbox". Debajo, una barra de captura que guarda con Enter. Después, los ítems agrupados por antigüedad (Today, This week, Older) en filas simples. El modo "Process" muestra un ítem por vez en un panel centrado.

Decisiones:

- **Captura con Enter:** sin botón "Add to Inbox" separado; un aviso de tecla Enter dentro de la barra.
- **Acciones por fila:** "Make task", "Make note" y un ícono de borrar aparecen al pasar el mouse o al enfocar la fila con el teclado; en mobile se ven siempre. Borrar no usa rojo.
- **Modo "Process":** contador y barra ("2 of 4"), el texto del ítem grande, "Captured N ago", tres acciones con atajos de teclado (T, N, D), "Skip" y cerrar. "Make task" y "Make note" reutilizan los modales de convertir que ya existen. Al terminar el último ítem, muestra el estado vacío.
- **Estado vacío "Inbox zero":** un mensaje que explica para qué sirve el Inbox y el foco puesto en la barra de captura.
- **Datos:** no se agregan columnas; los grupos salen de `created_at`.

Diferencias de Stitch que se corrigen en el código:

- [x] En el sidebar, "Inbox" no aparece como ítem activo en ninguna de las dos pantallas: debe usar el resaltado activo.
- [x] "FunWork Studio" al pie del sidebar y el cohete del selector de proyecto: igual que en el Home, se quitan o se usa el ícono real.
- [x] Los atajos T, N y D deben funcionar solo dentro del modo "Process", con Escape para cerrar, y nunca mientras se escribe en un campo.
- [x] Definir el modo oscuro, mobile (el panel de "Process" ocupa casi toda la pantalla y los botones se apilan) y el estado vacío.

## Tablero de tareas: decisiones y ajustes para el código

Diseño aprobado: "Un tablero que se usa con las manos". Un encabezado compacto en una línea (título con total, filtros por área, selector Board/List y "New task"), columnas neutras con todas las tareas visibles, arrastrar y soltar, y el detalle en un panel lateral.

Decisiones:

- **Todas las tareas visibles:** se elimina el `limit(3)` de `⚡task-board.blade.php` y el botón "+N more tasks". Cada columna hace scroll por su cuenta.
- **Arrastrar y soltar** con `wire:sort` de Livewire v4: entre columnas cambia el estado y dentro de una columna cambia el orden. Durante el arrastre, la tarjeta se levanta y la columna de destino muestra dónde va a caer.
- **Columnas neutras** (`surface-low`): el color de estado queda solo en el punto del encabezado.
- **Tarjetas:** título, etiqueta de área, prioridad solo cuando es High, y fecha solo si existe ("Overdue" en rojo). Sin avatar (hoy está fijo el de Caleb Porzio), sin ícono de expandir y sin "Due:" vacío.
- **Panel lateral de detalle** al hacer clic en una tarjeta, sin cambiar de página: selector de estado, título editable, propiedades (área, prioridad, fecha, creada), descripción en markdown, "Delete task" en gris y "Save".
- **Sin el título "Your Tasks, Organized."**

Diferencias de Stitch que se corrigen en el código:

- [x] Los colores de los puntos de estado no coinciden entre el tablero y el panel (Doing es azul en el tablero y amarillo en el panel): usar siempre los colores de `TaskStatus`.
- [x] En el sidebar, el ícono de Decisions cambió a un gráfico de barras: mantener el ícono actual.
- [x] "Newtask" aparece sin espacio: el botón dice "New task".
- [x] En el panel, la fecha de entrega muestra solo "Overdue": debe mostrar la fecha y marcarla como vencida (por ejemplo "Nov 20, overdue").
- [x] La checklist de la descripción sale del markdown (`- [ ]` / `- [x]`); marcarla desde el panel es opcional y no debe romper el markdown guardado.
- [x] La zona "Drop here to complete" solo aparece mientras se arrastra.
- [x] Definir la vista de lista, el modo oscuro, mobile (columnas con scroll horizontal o una columna con selector de estado; el panel ocupa toda la pantalla) y los estados vacíos de cada columna.
- [x] Accesibilidad: mover tareas sin mouse (por ejemplo, el selector de estado del panel) y anunciar el cambio.

## Layout del proyecto y ajustes del Home (segunda pasada)

Referencia: el sidebar y la barra superior de "Home - Website Relaunch" en Stitch. No se itera más en Stitch: el ancho y la altura de columnas dependen del tamaño de pantalla y de los datos, así que se validan en la app, con capturas a 1440 y 2560 px en claro y oscuro, antes de mergear.

Cambios:

- [x] **Sidebar más legible** (afecta a todas las pantallas del proyecto): texto de unos 16 px, íconos de 20 px, filas más altas (al menos 44 px en mobile) y el logo con más peso. Se mantienen el resaltado activo, Change Log y el crédito del pie, en tamaño discreto.
- [x] **Barra superior sin repetir el proyecto:** el nombre aparece una sola vez, en un selector con forma de píldora a la izquierda con el ícono real del proyecto. A la derecha quedan "Areas", la campana, el nombre del usuario y el avatar. Se quita el título duplicado.
- [x] **Home más ancho:** el contenido pasa del ancho actual (unos 1000 px) a `max-w-7xl`, para que no queden franjas vacías en pantallas anchas.
- [x] **Columnas del Home de igual altura:** "Today" se estira a la altura de la columna derecha. Si sobra espacio, muestra las próximas tareas pendientes ("Upcoming"), nunca contenido inventado.
- [x] **Áreas sin tareas:** en lugar de "0 of 0 tasks done" y una barra vacía, dicen "No tasks yet".

No se toma de Stitch: la tarjeta "Design guidelines", el anillo de progreso ni el texto del sprint.

## Notebooks: decisiones y ajustes para el código

Diseño aprobado: "Una biblioteca de documentos del proyecto". La lista de notebooks es densa, en filas y con datos reales. Al abrir un notebook se ven dos paneles: a la izquierda el índice de páginas y a la derecha la página, con Write/Preview.

Decisiones:

- **Datos reales en cada fila:** nombre, descripción, área, cantidad de páginas, última edición real (`updated_at` del notebook o de su página más reciente) y estado: "From repo docs" cuando las páginas tienen `source_path`, y "Unsynced changes" cuando `hasUnsyncedChanges()` es verdadero. Se eliminan el "Modified at 2h" y el avatar de Caleb Porzio que están fijos en `⚡show-notebooks.blade.php`.
- **Menú "⋯" en inglés:** Export as Markdown, Change area, Sync with Google Docs y Open in Google Docs (este último solo si hay `google_doc_id`). La fecha de la última sincronización se muestra en inglés.
- **Notebook abierto en dos paneles:** se reutiliza el editor actual (markdown, Write/Preview, autoguardado con reintento, anterior/siguiente, "N of M", crear y borrar páginas). El modo de escritura enfocada (`layouts.focus`) se mantiene como opción.
- **Estado vacío:** una línea que explica qué es un notebook, un botón "Create your first notebook" y la pista de importar los docs del repo con `funwork:sync-docs`. Sin el título de dos colores ni la tarjeta de tip.

Diferencias de Stitch que se corrigen en el código:

- [ ] El índice de páginas lleva números (01–06): se quitan; el orden ya lo da la lista.
- [ ] La página actual del índice no usa el resaltado del sistema: fondo blanco y texto naranja, como el ítem activo del sidebar.
- [ ] El contador dice "2 of 12", pero el índice muestra 6 páginas: el índice lista todas las páginas (con scroll) y el contador coincide con la posición real.
- [ ] "History" al pie de la página: FunWork no tiene historial de versiones; se quita.
- [ ] El bloque de código muestra un nombre de archivo y "PHP 8.2" inventados: el bloque renderiza solo lo que trae el markdown.
- [ ] La lista con viñetas aparece sin viñetas: usar los estilos de `.markdown-content`.
- [ ] La etiqueta "Backend" del notebook abierto perdió su punto de color.
- [ ] Definir el modo oscuro y mobile: en mobile, el índice de páginas pasa a un selector o a un panel desplegable arriba de la página.

## Decisions: decisiones y ajustes para el código

Diseño aprobado (generado desde cero en Stitch): "El registro de por qué el proyecto es así". Pantallas: "Decisions - Website Relaunch" (lista) y "Decision Detail - Livewire Single-File Components" (detalle).

Decisiones:

- **Lista como registro:** filas con número, título, estado (punto y texto) y fecha. Las decisiones reemplazadas quedan atenuadas y muestran "Superseded by #N" como enlace.
- **Filtros en chips con contador** en lugar del select actual, más una búsqueda por título y contenido.
- **Detalle en dos columnas:** lectura (Context, Decision, Consequences) a ancho cómodo y un panel lateral fijo con estado, fecha, autor (`created_by`), la cadena de reemplazos en las dos direcciones ("Supersedes" y "Superseded by") y las acciones "Edit" y "Supersede".
- **Decisión reemplazada:** aviso arriba de la lectura, "This decision was superseded by #N {title}", con enlace.
- **Estado vacío:** "Record why the project is built this way", un botón y la pista de registrarla desde el agente con `log_decision`.
- **Autor real:** se muestra `created_by`. No se muestra "Logged by MCP agent" porque hoy no se guarda el origen.

Diferencias de Stitch que se corrigen en el código:

- [ ] En los chips, el contador se parte en una segunda línea ("Accepted / 8"): va en la misma línea.
- [ ] Falta el chip "Rejected": los filtros cubren los cuatro estados de `DecisionStatus` y ocultan los que tienen cero.
- [ ] "New decision" y el subtítulo se parten en dos líneas: el botón ocupa una línea y el subtítulo tiene ancho suficiente.
- [ ] Las filas reemplazadas repiten "Superseded" y "Superseded by #N": queda solo el enlace.
- [ ] "Edit" y "Supersede" en el panel parecen texto plano: se ven como botones secundarios.
- [ ] "Supersede" hoy no es una acción propia: el reemplazo se elige en el formulario de edición (`form.supersededById`). El botón abre ese flujo con el campo preseleccionado, sin crear lógica nueva.
- [ ] En el sidebar, el ícono de Inbox cambió a un sobre: mantener el ícono actual.
- [ ] La sección "Preview" de la pantalla de detalle es solo una muestra de Stitch: en la app es el mismo detalle con el aviso de reemplazo.
- [ ] Definir el modo oscuro y mobile (el panel lateral pasa debajo del título) y el estado vacío.

## Archivos: decisiones y ajustes para el código

Diseño aprobado: "Los archivos del proyecto, a la vista". Arriba el encabezado con filtros por tipo, búsqueda, el selector grilla/lista, "New folder" y "Upload", con los breadcrumbs debajo. Luego las carpetas y, después, los archivos con miniaturas. Al arrastrar archivos sobre la página, aparece una capa para soltarlos y subirlos a la carpeta actual.

Decisiones:

- **El selector grilla/lista funciona:** la lista es una tabla con nombre, tipo, tamaño, fecha y carpeta.
- **Filtros por tipo** con `FileCategory` (All, Images, PDFs, Documents).
- **Miniaturas reales** para las imágenes; ícono por tipo con fondo suave para PDF (rojo) y documentos (azul).
- **Arrastrar para subir:** la capa dice "Drop to upload to {carpeta}" y muestra el límite real que valida la subida (el "20 MB" de Stitch es un ejemplo). Reutiliza el flujo de subida existente.
- **Se mantienen** la selección múltiple con barra flotante, mover, renombrar, borrar con confirmación y el lightbox.
- **Sin el panel gris** que envuelve toda la página y sin el título en dos colores del estado vacío ("Upload" principal, "New folder" secundario y la pista "or drag files here").

Diferencias de Stitch que se corrigen en el código:

- [ ] "Upload" no usa el botón principal: va con el degradado de la marca.
- [ ] El chip "All" casi no se ve seleccionado: usar el mismo estilo de chip seleccionado que Tasks y Notebooks.
- [ ] La tarjeta seleccionada tiene borde azul y el checkbox no se ve: borde naranja (`brand`) y checkbox marcado visible.
- [ ] Los PDF dicen "Document" y "Executive brief" debajo del ícono: la etiqueta dice el tipo real ("PDF", "Word document").
- [ ] "Sorted by recent" no existe como opción: se quita, salvo que se agregue un orden real.
- [ ] "1 selected" se parte en dos líneas en la barra flotante.
- [ ] En el sidebar, el ícono de Inbox cambió a un sobre: mantener el ícono actual.
- [ ] Las tarjetas de carpeta tienen muy poco contraste con el fondo.
- [ ] Definir el modo oscuro, mobile (dos columnas de archivos, encabezado en dos filas) y el estado vacío.

## Settings de la cuenta: decisiones y ajustes para el código

Diseño aprobado: "Settings que se sienten parte de FunWork". Barra superior propia de la app (logo, campana, avatar), "Back to projects", contenido centrado, navegación lateral con íconos y estado activo, y cada sección en una tarjeta con su propio "Save".

Decisiones:

- **Profile:** avatar con iniciales de respaldo (hoy se ve roto cuando falla el avatar externo), "Upload photo" con vista previa y "Remove", campos en dos columnas y "Saved" junto a "Save".
- **Danger zone separada:** "Delete account" va como botón secundario con texto rojo; el rojo sólido solo aparece en el modal de confirmación.
- **Integrations como pieza central:** tarjeta "Connect your AI agent" con la URL real del servidor MCP (`Mcp::web('/mcp/funwork', ...)` en `routes/ai.php`, con el dominio de `APP_URL`), un bloque de configuración para copiar, la tabla de tokens (Name, Last used, Created, Revoke) y la tarjeta de Google Docs.
- **Se mantienen** Security (contraseña y 2FA), Appearance y Notifications con el mismo formato de tarjetas.

Diferencias de Stitch que se corrigen en el código:

- [ ] La barra superior agrega enlaces "Account Settings, Security, Billing, Notifications": se quitan; la navegación vive solo en la columna izquierda y FunWork no tiene "Billing".
- [ ] La URL del servidor MCP de Stitch (`https://funwork.test/mcp`) es un ejemplo: se usa la URL real, `/mcp/funwork`.
- [ ] El bloque de configuración se titula "claude_desktop_config.json": usar un título genérico ("MCP client configuration") y verificar que el formato del snippet sea el que aceptan los clientes que usamos (Claude Code, Antigravity).
- [ ] La columna "Action" de la tabla de tokens no necesita título.
- [ ] Definir el modo oscuro y mobile (la navegación lateral pasa a pestañas o a un selector arriba).

## Settings del proyecto (pantalla nueva): decisiones y ajustes para el código

Diseño aprobado: una página de settings dentro del proyecto, con un ítem "Settings" (ícono de engranaje) al pie del sidebar del proyecto. Tarjetas "General", "Areas" y "Danger zone".

Decisiones:

- **General:** ícono del proyecto (el campo `icon` ya existe en `projects`), nombre, descripción, tipo (`ProjectType`), estado (`ProjectStatus`) y fecha de entrega, con "Saved" junto a "Save". Reutiliza las validaciones existentes de proyecto.
- **Areas:** reemplaza o reutiliza el modal actual `⚡manage-areas`: lista con arrastrar para reordenar, punto de color, nombre, cantidad de tareas, "Edit", borrar y "Add area".
- **Danger zone:** "Archive project" (usa `archived_at`, reversible) y "Delete project" con confirmación. Solo el dueño puede verla y usarla.
- **Sin miembros ni invitaciones:** hoy cada proyecto tiene un solo dueño.

Diferencias de Stitch que se corrigen en el código:

- [ ] El eyebrow "PROJECT CONFIGURATION • Settings & taxonomies" en mayúsculas: se quita; queda solo el título "Project settings".
- [ ] La píldora "Scoped to Website relaunch" repite lo que ya dice el selector de proyecto: se quita.
- [ ] La tarjeta "General" tiene una línea naranja superior: se quita.
- [ ] "ID: PRJ-RELAUNCH-01" es un identificador inventado: se quita.
- [ ] "Change icon" abre un selector con los íconos que ya soporta `icon-project`; no se suben imágenes.
- [ ] El acceso a Areas de la barra superior lleva a esta tarjeta en lugar de abrir el modal suelto, o se mantiene el modal y se enlaza desde acá: decidir uno solo para no duplicar.
- [ ] Definir el modo oscuro y mobile.

## Pendientes

- [ ] Documentar en Stitch las pantallas restantes con el mismo flujo.
