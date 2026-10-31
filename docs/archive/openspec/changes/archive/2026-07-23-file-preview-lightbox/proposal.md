# Proposal: File Preview Lightbox

## Intent
Provide a unified, responsive file preview lightbox system, allowing users to quickly view standard files (Images, safe SVGs, PDFs on Desktop) up to 15MB without downloading them, while providing a clean fallback download experience for unsupported or excessively large files.

## Scope

### In Scope
- Full-screen modal viewer with backdrop blur and focus trapping.
- Viewing <15MB images and SVGs securely via `<img>` tag.
- Native PDF rendering on Desktop (<15MB).
- Clean fallback UI for large files (>15MB), unsupported formats (e.g. zip, docx), or Mobile PDFs.
- Next/Previous file navigation within the current view without server roundtrips.
- Skeleton loader/spinner during high-res asset load.
- 100% Alpine.js/Livewire Flux Pro implementation using anonymous Livewire component architecture.

### Out of Scope
- Server-side view metrics or analytics.
- Direct rendering of complex documents (ZIP, DOCX, XLSX).
- Native PDF rendering on mobile devices.

## Capabilities
> This section is the CONTRACT between proposal and specs phases. The sdd-spec agent reads this to know exactly which spec files to create or update.

### New Capabilities
- `view-files-lightbox`: The system MUST allow users to preview supported files in a responsive lightbox, and gracefully fallback to a download prompt for unsupported/large files.

### Modified Capabilities
None.

## Approach
Create an anonymous Livewire component (`⚡file-preview-lightbox.blade.php`) utilizing `<flux:modal>` for accessibility and backdrop blur. Client-side state (current file, loading status, next/previous logic) will be managed via `x-data` in Alpine.js to eliminate server roundtrips while navigating between files. Conditional rendering logic will be used based on file type and size. The UI will detect mobile browsers to force the fallback download view for PDFs. 

## Affected Areas
| Area | Impact | Description |
|------|--------|-------------|
| `resources/views/components/` | New | Create generic `⚡file-preview-lightbox.blade.php` |
| `resources/views/pages/` | Modified | Add modal trigger points to file listing views. |

## Risks
| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Mobile PDF rendering failures | High | Force fallback download view for all PDFs on mobile devices. |
| Security with SVG files | Med | Strictly render SVGs via `<img>` tags to prevent script execution. |
| Heavy memory usage | Low | Enforce a strict 15MB file size limit for previews before defaulting to the fallback UI. |

## Rollback Plan
Revert the commits adding the `⚡file-preview-lightbox.blade.php` component and remove the `<flux:modal.trigger>` entries from the respective file list views.

## Dependencies
- Livewire v4
- Flux Pro UI components
- Alpine.js v3

## Success Criteria
- [ ] Images and SVGs (<15MB) open instantly in a responsive, full-screen modal.
- [ ] Desktop users can view PDFs (<15MB) natively in the modal.
- [ ] Mobile users attempting to view PDFs see the clean fallback download UI.
- [ ] Unsupported files or files >15MB immediately show the fallback download UI.
- [ ] Users can navigate previous/next files seamlessly via arrows.
- [ ] Pressing ESC closes the modal, trapping focus appropriately.
