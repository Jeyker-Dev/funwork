# ADR-003: Sincronizar el Project pack con Google Docs mediante OAuth por usuario

- **Fecha:** 2026-11-05
- **Estado:** Aceptada

## Contexto

NotebookLM no tiene API para cuentas personales (ADR-002). La única fuente que NotebookLM puede refrescar con "Sync" es un Google Doc o Slides. El Project pack (`BuildProjectPackAction`) ya se descarga como `.md`, pero subirlo a mano genera una fuente nueva en cada cambio.

Según la guía "Manage uploads" de la API de Drive, la importación a Google Docs acepta Markdown, y un `update` con conversión reemplaza todo el contenido del Doc.

## Decisión

1. **OAuth por usuario** (elegido por el usuario el 2026-11-05) en lugar de una service account: cada usuario conecta su cuenta y el Doc se crea en su Drive.
2. **Scope `drive.file`**: la app solo accede a los archivos que crea. No es un scope sensible, así que no requiere la verificación de Google.
3. **`laravel/socialite`** para el flujo OAuth (state/CSRF) y el cliente `Http` de Laravel para Drive, sin `google/apiclient`.
4. **Un Doc por proyecto** (`projects.google_doc_id`). Las sincronizaciones posteriores reemplazan su contenido y conservan el ID.
5. Los tokens se guardan cifrados (`encrypted`), y la sincronización es síncrona, iniciada por el usuario.

## Consecuencias

- Hace falta un proyecto en Google Cloud con la pantalla de consentimiento publicada; en modo Testing los refresh tokens vencen a los 7 días.
- Rotar `APP_KEY` invalida los tokens guardados: los usuarios tienen que volver a conectar Google.
- Si el dueño del proyecto conecta otra cuenta de Google, el Doc anterior queda inaccesible (`drive.file`) y se crea uno nuevo.
- La alternativa con service account era más simple (sin pantalla de login ni tokens por usuario), pero no escala a varios usuarios.

> [!NOTE]
> **Actualización (2026-11-06):** Ver [ADR-004](ADR-004-notebook-export-google-docs-sync.md). Se reemplazó la sincronización global del proyecto (`projects.google_doc_id`) por sincronización granular por notebook (`notebooks.google_doc_id`).

