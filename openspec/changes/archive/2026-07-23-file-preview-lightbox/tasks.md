# Tasks: File Preview Lightbox

## Review Workload Forecast

Decision needed before apply: No
Chained PRs recommended: No
Chain strategy: pending
400-line budget risk: Low

## Phase 1: Foundation (Backend & Routes)
- [x] 1.1 Create `App/Http/Controllers/ProjectFilePreviewController` (invokable) to stream files inline (`Content-Disposition: inline`).
- [x] 1.2 Register `GET /projects/{project}/files/{projectFile}/preview` in `routes/web.php` with appropriate middleware/policies.
- [x] 1.3 Create feature test `tests/Feature/ProjectFilePreviewTest.php` to verify inline streaming and security policies.

## Phase 2: Core Component Structure
- [x] 2.1 Create anonymous component `resources/views/components/projects/files/⚡file-preview-lightbox.blade.php` with a `public array $files` property.
- [x] 2.2 Implement Alpine.js `x-data` to manage `currentIndex`, check 15MB limits, validate types, and detect mobile (`/Mobi|Android/i.test(navigator.userAgent)`).
- [x] 2.3 Add `<flux:modal>` wrapper with focus trapping and ESC key closing capabilities.

## Phase 3: Lightbox UI & Rendering
- [x] 3.1 Implement `<img src>` rendering for valid images and SVGs with a loading skeleton/spinner state.
- [x] 3.2 Implement `<object>` rendering for Desktop PDFs.
- [x] 3.3 Build fallback UI with a download button for unsupported formats, large files (>15MB), and mobile PDFs.
- [x] 3.4 Add Next and Previous navigation controls bound to Alpine state for seamless client-side transitions.

## Phase 4: Integration
- [x] 4.1 Update `resources/views/pages/projects/files/⚡index.blade.php` to map `$files` to an array (id, name, mime, size, url) and inject `<livewire:projects.files.file-preview-lightbox :files="..." />`.
- [x] 4.2 Update `resources/views/components/projects/files/file-card.blade.php` to dispatch `open-lightbox` Alpine event with the file ID on click.
