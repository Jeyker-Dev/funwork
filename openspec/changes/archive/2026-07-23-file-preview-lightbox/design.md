# Design: File Preview Lightbox

## Technical Approach
Implement a client-side file preview lightbox using an anonymous Livewire component (`⚡file-preview-lightbox.blade.php`) and Alpine.js. The parent file listing view will pass the current page's files to the lightbox as a JSON array. Alpine.js will manage the state (`currentIndex`, mobile detection, navigation) to enable instant, zero-roundtrip navigation between files. A new route will be added to securely stream file content inline without triggering a download.

## Architecture Decisions

### Decision: Client-Side State for Navigation
**Choice**: Pass the current page's files array to the Livewire component and sync it with Alpine's `x-data`. Navigation (next/prev) simply increments/decrements a local index.
**Alternatives considered**: Triggering a Livewire roundtrip on every "Next" / "Previous" click to fetch the next file URL.
**Rationale**: The spec strictly requires "without server roundtrips" for navigation. By passing the file list to the client once per page load, we achieve instant transitions.

### Decision: File Streaming Endpoint
**Choice**: Create a new route (`project.files.preview`) that returns a streaming response (`Storage::response()`) for local files, and redirects to a temporary URL with `inline` Content-Disposition for S3 files.
**Alternatives considered**: Base64 encoding the file data directly in the DOM.
**Rationale**: Base64 encoding files up to 15MB would cause massive memory bloat and HTML payload size issues. A dedicated endpoint securely serves the file leveraging browser-level caching.

### Decision: Mobile PDF Rendering
**Choice**: Use Alpine feature detection (`/Mobi|Android/i.test(navigator.userAgent) || window.innerWidth < 768`) to detect mobile environments and force a fallback UI for PDFs.
**Alternatives considered**: Integrating a heavy JS library like PDF.js to force rendering on mobile.
**Rationale**: The spec mandates a clean fallback download UI for mobile PDFs to keep the component lightweight. Native `<object>` tags are unreliable for PDFs on mobile devices.

## Data Flow
1. **Init**: The orchestrator (`⚡index.blade.php`) renders, mapping the paginated files into a minimal array (id, name, mime_type, size, preview_url) and passes it to `<livewire:projects.files.file-preview-lightbox :files="$filesArray" />`.
2. **Trigger**: User clicks a `file-card`. It dispatches an Alpine event: `$dispatch('open-lightbox', { id: fileId })`.
3. **Open**: The Lightbox Alpine component catches the event, finds the file index, sets `currentIndex`, checks constraints (size < 15MB, valid type, mobile check), and opens the Flux modal.
4. **View**: The `<img>` or `<object>` tag dynamically binds to the `preview_url`. A fallback view with a download button renders if constraints fail.
5. **Navigate**: Next/Prev arrows adjust `currentIndex`. The DOM updates reactively.

## File Changes
| File | Action | Description |
|------|--------|-------------|
| `routes/web.php` | Modify | Add `GET /projects/{project}/files/{projectFile}/preview` route using a closure or invokable controller to stream the file inline. |
| `resources/views/components/projects/files/⚡file-preview-lightbox.blade.php` | Create | New anonymous Livewire component containing the Alpine `x-data` logic, `<flux:modal>`, image/object renderers, and fallback UI. |
| `resources/views/pages/projects/files/⚡index.blade.php` | Modify | Map `$files` to an array and pass it to the new `<livewire:...>` component. |
| `resources/views/components/projects/files/file-card.blade.php` | Modify | Add `x-on:click="$dispatch('open-lightbox', { id: {{ $file->id }} })"` to the card container. |

## Interfaces / Contracts

**Lightbox File Array Schema:**
```json
[
  {
    "id": 1,
    "name": "design.png",
    "mime_type": "image/png",
    "size": 2500000,
    "preview_url": "/projects/1/files/1/preview"
  }
]
```

**Alpine State Contract (`x-data`):**
```javascript
{
    files: @entangle('files'),
    currentIndex: -1,
    
    get currentFile() { ... },
    get canPreview() { /* checks 15MB limit and mime types */ },
    get isMobile() { /* checks userAgent and width */ },
    
    open(id) { ... },
    next() { ... },
    prev() { ... },
    downloadCurrent() { /* Triggers Livewire download */ }
}
```

## Testing Strategy
| Layer | What to Test | Approach |
|-------|-------------|----------|
| Unit (Feature) | Preview Endpoint | Test `GET project.files.preview` returns 200 and correct `Content-Disposition: inline` headers for both local and S3 files. |
| Unit (Feature) | Endpoint Security | Test that unauthorized users cannot access the preview route. |
| Unit (Livewire) | Component Rendering | Assert `⚡index.blade.php` correctly serializes the files array and passes it to the lightbox component. |

## Migration / Rollout
No database migrations required. The feature is safely scoped to the frontend components and a single read-only route.

## Open Questions
- [ ] If a user navigates to the *last* file on the current page, should the lightbox automatically fetch the next page of files? (Currently out of scope, navigation loops within the current pagination page).
