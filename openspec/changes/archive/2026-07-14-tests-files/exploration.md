## Exploration: tests-files

### Current State
El módulo de archivos está integrado en los proyectos y permite crear carpetas, subir archivos, navegar entre ellos, seleccionar elementos y descargar un ZIP con los elementos seleccionados. 
Actualmente consta de:
- Un componente Livewire principal (`resources/views/pages/projects/files/⚡index.blade.php`) que maneja el estado (archivos subidos, carpetas, selecciones, navegación) y la lógica de base de datos/almacenamiento directamente.
- Modelos Eloquent: `ProjectFile` y `Folder` (relacionados con `Project`).
- Una acción: `App\Actions\Files\DownloadFilesZipAction` encargada de generar un `.zip` temporal con los archivos y carpetas seleccionados y retornarlo como descarga.

### Affected Areas
- `tests/Feature/Projects/FilesTest.php` (nuevo) — Contendrá los tests para el componente Livewire y la navegación/gestión de archivos.
- `tests/Feature/Actions/DownloadFilesZipActionTest.php` (nuevo) — Contendrá los tests aislados para la acción de generación de ZIP.

### Approaches
1. **Testear todo a través del Componente Livewire (Integration Testing)**
   - Pros: Simula de forma cercana la interacción del usuario. Garantiza que la UI y el backend están bien conectados.
   - Cons: Puede ser engorroso asertar la descarga del ZIP mediante Livewire en comparación con un test directo de la Acción.
   - Effort: Medium

2. **Testear el Componente Livewire por un lado y la Acción por separado (Unit + Integration)**
   - Pros: Se alinea con buenas prácticas. Testeamos la carga de archivos, navegación y creación de carpetas vía Livewire (usando `Livewire::test`), mockeando `Storage::fake()`. La generación de ZIP se testea llamando directamente a `DownloadFilesZipAction` para validar que la respuesta es un archivo a descargar, sin pasar por la capa HTTP/Livewire.
   - Cons: Requiere escribir dos archivos de test separados, pero queda mucho más modular.
   - Effort: Medium

### Recommendation
Recomiendo la **Opción 2**. 
- Usaremos Pest con la sintaxis `test('it ...')` en `tests/Feature/`.
- Usaremos `Storage::fake('local')` para evitar escribir archivos reales durante los tests de subida.
- Para el componente Livewire (`pages::projects.files.index`), validaremos:
  - Navegación de carpetas (`assertSet('currentFolderId')`).
  - Subida de archivos (asertando creación en BD y en Storage).
  - Creación de carpetas.
  - Alternancia de selecciones (arquitectura actual maneja los IDs en arreglos).
- Para la acción `DownloadFilesZipAction`, crearemos un test dedicado que inyecte un `Project` ficticio y un array de IDs, verificando que la respuesta es un `BinaryFileResponse` (descarga del ZIP).

Adicionalmente, se nota que el componente Livewire tiene lógica de validación y creación directa de Eloquent (`createFolder` y `uploadFiles`). Aunque el objetivo es crear tests, bajo el estándar `laravel-clean-architecture`, esto eventualmente debería migrar a un Form Object o un Action, pero por ahora podemos cubrirlo con tests de Feature sin refactorizar.

### Risks
- La acción `DownloadFilesZipAction` interactúa directamente con `ZipArchive` y escribe en `storage_path('app/temp/...')`. En entorno de tests, esto podría escribir fuera del fake disk si no se maneja con cuidado, ya que utiliza `Storage::disk('local')->exists` pero luego usa `storage_path()`. Se debe tener precaución de limpiar los ZIPs temporales después del test o asegurar que el test apunte al disco local correctamente sin contaminar datos reales.
- Las subidas usan el disk 'local'. Hay que usar `Storage::fake('local')` en los tests para interceptarlas sin problema.

### Ready for Proposal
Yes — The orchestrator can tell the user we are ready to spec/task this out using the recommended Pest approach.
