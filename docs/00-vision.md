# Visión

> Borrador inferido del código existente. Las secciones marcadas **(por confirmar)** las tiene que completar el dueño del producto.

## Qué es

FunWork es una aplicación web para **administrar proyectos personales**. Cada proyecto reúne en un solo lugar sus tareas, ideas sin procesar, notas y archivos.

## Problema (por confirmar)

El contexto de un proyecto queda disperso entre herramientas (gestor de tareas, notas, almacenamiento de archivos, chats). FunWork lo centraliza por proyecto.

## Usuario objetivo (por confirmar)

Desarrollador o creador individual que lleva varios proyectos personales en paralelo.

## Módulos actuales

| Módulo | Propósito |
|---|---|
| Proyectos | Crear, listar y buscar proyectos (nombre, descripción, icono, tipo, estado, fechas). |
| Dashboard del proyecto | Captura rápida, estadísticas, tareas urgentes y actividad reciente. |
| Inbox | Capturar ideas rápidas y convertirlas después en tareas u otros elementos. |
| Tareas | Tablero por estados con drag & drop, varios asignados por tarea y slider con filtros. |
| Notebooks | Cuadernos con páginas y editor. |
| Archivos | Carpetas, subida, previsualización, mover, renombrar, búsqueda global y descarga en ZIP (asíncrona para carpetas). |
| Cuenta | Autenticación (Fortify), 2FA, perfil con avatar en S3 y apariencia. |

## Objetivos (por confirmar)

- [ ] Objetivo 1:
- [ ] Objetivo 2:

## Fuera de alcance (por confirmar)

- Colaboración en tiempo real entre varios usuarios.
- Búsqueda full-text dentro del contenido de los archivos.

## Visión a largo plazo

FunWork quiere ser el administrador y creador de aplicaciones más completo: el **hub de contexto** de cada proyecto, donde cada IA trabaja en su especialidad sobre los mismos datos.

| Módulo | Inspiración | Qué aporta |
|---|---|---|
| Cerebro | NotebookLM | Preguntar y analizar sobre las notas, decisiones y docs del proyecto. |
| Código | Antigravity | Un agente de código que toma las tareas del proyecto y actualiza su estado. |
| Diseño de UI | Stitch | Las pantallas del proyecto con su referencia de diseño. |
| Diseño de base de datos | (sin herramienta fija) | El modelo de datos del proyecto (tablas y relaciones). |

### Principios

- **App local no es lo mismo que modelo local.** No hace falta correr modelos en la propia máquina: los agentes (Claude Code, Codex, Antigravity) son programas locales que usan modelos en la nube con la suscripción del usuario. Así funciona T3 Code: es una interfaz que lanza las CLIs oficiales ya logueadas, con un adaptador por proveedor.
- **El servidor nunca usa la suscripción de un usuario.** FunWork corre en un VPS y es multiusuario. Usar desde el servidor la suscripción de Gemini, Antigravity o Claude de otra persona viola los términos de Google y Anthropic, y Google suspendió cuentas por eso en 2026. El chat web de Gemini no tiene API.
- **Puertos y adaptadores desde el día uno.** Cada módulo de IA depende de un puerto (por ejemplo `AiProvider`) y cada proveedor es un adaptador. Cambiar de proveedor nunca obliga a reescribir el módulo.
- **El usuario trae su IA.** Hay dos caminos legítimos:
  - **BYOK:** cada usuario guarda su propia API key, cifrada. Gemini en AI Studio tenía una capa gratuita; hay que verificar sus límites antes de diseñar sobre ella.
  - **Agentes locales:** el agente del usuario corre en su máquina y se conecta a FunWork.
- **Se prueba sin pagar modelos.** Los tests automáticos usan fakes (como `Http::fake` con Google Drive). Las pruebas manuales usan las cuentas que el usuario ya tiene.

### Orden de construcción

1. Terminar las fases actuales del roadmap.
2. **Servidor MCP en FunWork:** cualquier agente local (Antigravity, Claude Code, Codex) lee y actualiza tareas y notebooks. Es la base de todo y no cuesta nada en modelos.
3. **Puerto `AiProvider` con BYOK:** el primer adaptador es la Gemini API, para el módulo Cerebro.
4. **Companion local**, al estilo T3 Code: lanza los agentes del usuario en su máquina y se conecta a la web. Llega cuando los módulos de código y diseño lo necesiten.

Ya existen piezas sueltas de esta idea: Jira, Linear, Dart y Multica permiten asignar tareas a agentes de código. Ninguno junta cerebro, código y diseño (UI y base de datos) en un solo hub: ese es el diferencial de FunWork.
