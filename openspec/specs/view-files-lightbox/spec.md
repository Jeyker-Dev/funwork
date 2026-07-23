# View Files Lightbox Specification

## Purpose
A unified, responsive file preview lightbox system allowing users to view standard files (Images, safe SVGs, PDFs on Desktop) up to 15MB without downloading, while providing a clean fallback download UI for unsupported or excessively large files.

## Requirements

### Requirement: Preview Supported Images and SVGs
The system MUST render supported image formats and SVGs smaller than 15MB in a responsive full-screen modal viewer.

#### Scenario: User clicks on a supported image
- GIVEN a supported image file (e.g., PNG, JPG, WebP) or SVG under 15MB
- WHEN the user clicks to preview the file
- THEN the system MUST open a full-screen modal viewer with backdrop blur
- AND the image MUST be rendered securely via an `<img>` tag without triggering a download
- AND a skeleton loader or spinner MUST be visible during high-res asset loading.

### Requirement: Desktop PDF Native Rendering
The system MUST support native PDF rendering within the modal viewer for desktop users when the file is under 15MB.

#### Scenario: Desktop user opens a PDF
- GIVEN the user is on a desktop device
- AND the PDF file is under 15MB
- WHEN the user clicks to preview the PDF
- THEN the PDF MUST be rendered natively within the lightbox modal.

### Requirement: Mobile PDF Fallback
The system MUST NOT attempt to render PDFs natively on mobile devices and MUST gracefully fall back to a download prompt.

#### Scenario: Mobile user opens a PDF
- GIVEN the user is on a mobile device browser
- AND the file is a PDF
- WHEN the user clicks to preview the PDF
- THEN the system MUST display the fallback download UI instead of attempting to render the PDF natively.

### Requirement: Size and Unsupported Format Fallback
The system MUST display a fallback download UI for files exceeding 15MB or for unsupported file types (e.g., ZIP, DOCX, XLSX).

#### Scenario: File exceeds size limit
- GIVEN any file type
- AND the file size is strictly greater than 15MB
- WHEN the user clicks to preview the file
- THEN the system MUST display the fallback download UI.

#### Scenario: Unsupported file type
- GIVEN a file type that is not an Image, safe SVG, or PDF
- WHEN the user clicks to preview the file
- THEN the system MUST immediately show the fallback download UI.

### Requirement: Client-Side Navigation
The system MUST allow seamless navigation between adjacent files within the same view without requiring server roundtrips.

#### Scenario: User navigates next and previous
- GIVEN the modal viewer is open and displaying a file
- AND there are multiple files in the current file listing
- WHEN the user clicks the next or previous navigation arrows
- THEN the system MUST instantly update the viewer state (via Alpine.js client-side data) to display the adjacent file without reloading the component from the server.

### Requirement: Accessibility and Focus Trapping
The system MUST trap focus within the modal when open and support standard keyboard accessibility.

#### Scenario: User closes modal via keyboard
- GIVEN the full-screen modal viewer is open
- WHEN the user presses the ESC key
- THEN the modal MUST close
- AND focus MUST return to the underlying page trigger point appropriately.
