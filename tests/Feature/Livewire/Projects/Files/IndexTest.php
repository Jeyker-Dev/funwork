<?php

use App\Livewire\Forms\UploadFileForm;
use App\Models\Folder;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->project = Project::factory()->create([
        'owner_id' => $this->user->id,
    ]);
});

test('it renders successfully', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->assertOk()
        ->assertSee('Files');
});

test('it can create a folder', function () {
    $this->actingAs($this->user);

    Livewire::test('projects.files.⚡create-folder-modal', ['project' => $this->project])
        ->set('form.name', 'New Folder')
        ->call('save')
        ->assertHasNoErrors();

    expect(Folder::where('name', 'New Folder')->where('project_id', $this->project->id)->exists())->toBeTrue();
});

test('it can upload files', function () {
    $this->actingAs($this->user);
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $file = UploadedFile::fake()->image('test.jpg');

    Livewire::test('projects.files.⚡upload-file-modal', ['project' => $this->project])
        ->set('form.files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    expect(ProjectFile::where('name', 'test.jpg')->where('project_id', $this->project->id)->exists())->toBeTrue();

    $uploadedFile = ProjectFile::where('name', 'test.jpg')->first();
    Storage::disk($disk)->assertExists($uploadedFile->file_path);
});

test('it can download selected items', function () {
    $this->actingAs($this->user);
    Storage::fake('s3');
    Storage::fake('local'); // needed for zip if applicable

    $file = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'disk' => 'local',
        'file_path' => 'projects/'.$this->project->id.'/test.txt',
    ]);

    Storage::disk('local')->put($file->file_path, 'test content');

    $response = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['file_'.$file->id])
        ->call('downloadSelected');

    $response->assertHasNoErrors();
    $response->assertFileDownloaded();
});

test('it can delete selected items', function () {
    $this->actingAs($this->user);

    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'To Be Deleted',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['folder_'.$folder->id])
        ->call('deleteSelected')
        ->assertHasNoErrors()
        ->assertSet('selectedIds', []);

    // Verify it was soft deleted
    expect(Folder::withTrashed()->find($folder->id)->trashed())->toBeTrue();
});

test('it triggers move modal with selected items', function () {
    $this->actingAs($this->user);
    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Folder to move',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['folder_'.$folder->id])
        ->call('moveSelected')
        ->assertDispatched('open-move-modal', selectedIds: ['folder_'.$folder->id]);
});

test('it clears selection on items moved event', function () {
    $this->actingAs($this->user);
    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Folder to clear',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['folder_'.$folder->id])
        ->dispatch('items-moved')
        ->assertSet('selectedIds', []);
});

test('searching for percent wildcard returns only file with literal percent in name', function () {
    $this->actingAs($this->user);

    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'documento.pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'descuento_10%.pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', '%')
        ->assertSee('descuento_10%.pdf')
        ->assertDontSee('documento.pdf');
});

test('searching for underscore wildcard returns only test_file.pdf and not testfile.pdf', function () {
    $this->actingAs($this->user);

    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'test_file.pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'testfile.pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', '_')
        ->assertSee('test_file.pdf')
        ->assertDontSee('testfile.pdf');
});

test('searching for underscore wildcard also filters folders with and without underscore', function () {
    $this->actingAs($this->user);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'folder_one',
    ]);
    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'folderone',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', '_')
        ->assertSee('folder_one')
        ->assertDontSee('folderone');
});

test('regression: searching for normal text continues to return matching files and folders', function () {
    $this->actingAs($this->user);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'reportes_anuales',
    ]);
    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'otra_carpeta',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'reporte_mensual.pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'documento.pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', 'reporte')
        ->assertSee('reportes_anuales')
        ->assertSee('reporte_mensual.pdf')
        ->assertDontSee('otra_carpeta')
        ->assertDontSee('documento.pdf');
});

test('searching for exclamation mark does not break query and returns only items with exclamation mark', function () {
    $this->actingAs($this->user);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'urgente!',
    ]);
    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'general',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'importante!.pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'normal.pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('searchQuery', '!')
        ->assertSee('urgente!')
        ->assertSee('importante!.pdf')
        ->assertDontSee('general')
        ->assertDontSee('normal.pdf');
});

test('S1: header displays title, total file count, subtitle, FileCategory chips, search, view toggle, and action buttons without grey outer panel or sort label', function () {
    $this->actingAs($this->user);

    Folder::factory()->create(['project_id' => $this->project->id, 'created_by' => $this->user->id, 'name' => 'Design']);
    ProjectFile::factory()->count(2)->create(['project_id' => $this->project->id, 'created_by' => $this->user->id]);

    $response = $this->get(route('project.files', $this->project));

    $response->assertSuccessful()
        ->assertSee('Files')
        ->assertSee('All')
        ->assertSee('Images')
        ->assertSee('PDFs')
        ->assertSee('Documents')
        ->assertSee('New folder')
        ->assertSee('Upload')
        ->assertDontSee('Sorted by recent')
        ->assertDontSee('New File')
        ->assertDontSee('Search tasks');

    $html = $response->getContent();

    expect($html)->not->toContain('bg-surface-low border border-ghost rounded-2xl flex-1 flex flex-col overflow-hidden');
});

test('S1: clicking FileCategory chips filters files and applies selected chip styling', function () {
    $this->actingAs($this->user);

    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'hero.png',
        'mime_type' => 'image/png',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'brief.pdf',
        'mime_type' => 'application/pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'notes.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->call('selectCategory', 'image')
        ->assertSet('categoryFilter', 'image')
        ->assertSee('hero.png')
        ->assertDontSee('brief.pdf')
        ->assertDontSee('notes.docx')
        ->assertSeeHtml('data-category-chip="image"')
        ->assertSeeHtml('bg-ink text-surface-card')
        ->call('selectCategory', 'pdf')
        ->assertSee('brief.pdf')
        ->assertDontSee('hero.png')
        ->call('selectCategory', 'document')
        ->assertSee('notes.docx')
        ->assertDontSee('brief.pdf')
        ->call('selectCategory', '')
        ->assertSee('hero.png')
        ->assertSee('brief.pdf')
        ->assertSee('notes.docx');
});

test('S1: breadcrumbs appear below header when inside a folder and navigate back', function () {
    $this->actingAs($this->user);

    $parent = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Brand',
    ]);
    $child = Folder::factory()->create([
        'project_id' => $this->project->id,
        'parent_id' => $parent->id,
        'created_by' => $this->user->id,
        'name' => 'Logos',
    ]);

    $component = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->call('navigateToFolder', $child->id)
        ->assertSee('Brand')
        ->assertSee('Logos');

    $html = $component->html();
    expect(strpos($html, '</header>'))->toBeLessThan(strpos($html, 'data-files-breadcrumbs'));

    $component->call('navigateToRoot')
        ->assertSet('currentFolderId', null);
});

test('S2: renders folders before files, image thumbnails, tinted pdf/document icons, and real type labels without uppercase', function () {
    $this->actingAs($this->user);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Assets Folder',
    ]);
    $image = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'banner.png',
        'mime_type' => 'image/png',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'contract.pdf',
        'mime_type' => 'application/pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'proposal.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $html = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->assertSee('Assets Folder')
        ->assertSee('PDF')
        ->assertSee('Word document')
        ->assertSee('Image')
        ->assertSeeHtml(route('project.files.preview', [$this->project, $image]))
        ->assertSeeHtml('bg-red-50')
        ->assertSeeHtml('bg-blue-50')
        ->assertSeeHtml('shadow-ambient')
        ->html();

    expect(strpos($html, 'Assets Folder'))->toBeLessThan(strpos($html, 'banner.png'))
        ->and($html)->not->toContain('uppercase tracking-wide');
});

test('S2: list view displays table with name, type, size, date and folder with folders first', function () {
    $this->actingAs($this->user);

    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Specs',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'architecture.pdf',
        'mime_type' => 'application/pdf',
        'size' => 1572864,
    ]);

    $html = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->call('setView', 'list')
        ->assertSet('view', 'list')
        ->assertSeeHtml('<table')
        ->assertSee('Name')
        ->assertSee('Type')
        ->assertSee('Size')
        ->assertSee('Date')
        ->assertSee('Folder')
        ->assertSee('Specs')
        ->assertSee('architecture.pdf')
        ->assertSee('PDF')
        ->assertSee('1.50 MB')
        ->assertSee('Root')
        ->html();

    expect(strpos($html, 'Specs'))->toBeLessThan(strpos($html, 'architecture.pdf'));
});

test('S2: selected cards show brand border and checked checkbox, floating bar keeps N selected unwrapped in English, and delete modal is in English', function () {
    $this->actingAs($this->user);

    $file = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'selected-item.pdf',
        'mime_type' => 'application/pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('selectedIds', ['file_'.$file->id])
        ->assertSeeHtml('border-brand')
        ->assertSeeHtml('checked')
        ->assertSeeHtml('whitespace-nowrap')
        ->assertSee('Move')
        ->assertSee('Download')
        ->assertSee('Delete')
        ->assertDontSee('seleccionado(s)')
        ->assertDontSee('Mover')
        ->assertDontSee('Eliminar');

    Livewire::test('projects.files.⚡delete-confirmation-modal', [
        'project' => $this->project,
        'selectedFoldersCount' => 1,
        'selectedFilesCount' => 2,
    ])
        ->assertSee('Confirm deletion')
        ->assertSee('1 folder')
        ->assertSee('2 files')
        ->assertDontSee('Confirmar Eliminación');
});

test('S2: avoids N+1 queries across folders and files in grid, list, and search views', function () {
    $this->actingAs($this->user);

    $countQueries = function (int $itemCount): int {
        $project = Project::factory()->create(['owner_id' => $this->user->id]);

        foreach (range(1, $itemCount) as $index) {
            $folder = Folder::factory()->create([
                'project_id' => $project->id,
                'created_by' => $this->user->id,
                'name' => "Folder {$index}",
            ]);
            ProjectFile::factory()->create([
                'project_id' => $project->id,
                'folder_id' => null,
                'created_by' => $this->user->id,
                'name' => "File {$index}.pdf",
                'mime_type' => 'application/pdf',
            ]);
            ProjectFile::factory()->create([
                'project_id' => $project->id,
                'folder_id' => $folder->id,
                'created_by' => $this->user->id,
                'name' => "Nested {$index}.png",
                'mime_type' => 'image/png',
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test('pages::projects.files.index', ['project' => $project])
            ->call('setView', 'list')
            ->set('searchQuery', 'Nested');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return count($queries);
    };

    $queriesWithTwo = $countQueries(2);
    $queriesWithTen = $countQueries(10);

    expect($queriesWithTen)->toBeLessThanOrEqual($queriesWithTwo + 2);
});

test('S3: drag overlay shows current destination name and upload rule max size limit without hardcoded 20 MB', function () {
    $this->actingAs($this->user);

    $this->project->update(['name' => 'Website Relaunch']);
    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Brand Assets',
    ]);

    $expectedLimit = UploadFileForm::maxFileSizeLabel();

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->assertSee('Drop to upload to Website Relaunch')
        ->assertSee($expectedLimit)
        ->assertDontSee('20 MB')
        ->call('navigateToFolder', $folder->id)
        ->assertSee('Drop to upload to Brand Assets')
        ->assertSee($expectedLimit);
});

test('S3: dropping files reuses existing upload flow into root or current folder', function () {
    $this->actingAs($this->user);
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Drop Target',
    ]);

    $file = UploadedFile::fake()->image('dropped-banner.png');

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->call('navigateToFolder', $folder->id)
        ->set('uploadForm.files', [$file])
        ->call('saveDroppedFiles')
        ->assertHasNoErrors();

    $uploaded = ProjectFile::where('name', 'dropped-banner.png')->first();
    expect($uploaded)->not->toBeNull()
        ->and($uploaded->folder_id)->toBe($folder->id);
    Storage::disk($disk)->assertExists($uploaded->file_path);
});

test('S3: dropping oversized file fails validation without storing file', function () {
    $this->actingAs($this->user);
    $disk = config('filesystems.default');
    Storage::fake($disk);

    $oversizedFile = UploadedFile::fake()->create(
        'huge.bin',
        UploadFileForm::MAX_FILE_SIZE_KB + 1024,
        'application/octet-stream'
    );

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->set('uploadForm.files', [$oversizedFile])
        ->call('saveDroppedFiles')
        ->assertSet('uploadForm.files', []);

    expect(ProjectFile::where('name', 'huge.bin')->exists())->toBeFalse();
});

test('S4: empty state shows icon, single-tone title, help line, Upload primary button, New folder secondary button, and drag hint', function () {
    $this->actingAs($this->user);

    $component = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->assertSee('No files yet')
        ->assertSee('Upload')
        ->assertSee('New folder')
        ->assertSee('or drag files here')
        ->assertDontSee('No Files or Folders,');

    $emptyHtml = view('components.projects.files.empty-state')->render();
    expect($emptyHtml)->not->toContain('text-brand');
});

test('S4: shows no matching files state with clear filters when filter or search has zero results', function () {
    $this->actingAs($this->user);

    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'spec.pdf',
        'mime_type' => 'application/pdf',
    ]);

    Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->call('selectCategory', 'image')
        ->assertSee('No matching files')
        ->assertSee('Clear filters')
        ->call('resetFilters')
        ->assertSet('categoryFilter', '')
        ->assertSee('spec.pdf');
});

test('S5: renders two-column mobile grid, 44px mobile touch targets, and preserves inbox sidebar icon', function () {
    $this->actingAs($this->user);

    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'mobile-check.pdf',
        'mime_type' => 'application/pdf',
    ]);

    $response = $this->get(route('project.files', $this->project));

    $response->assertSuccessful()
        ->assertSeeHtml('grid-cols-2')
        ->assertSeeHtml('max-md:min-h-11');

    $sidebarHtml = view('layouts.app.sidebar', ['project' => $this->project])->render();
    expect($sidebarHtml)->toContain('Inbox');
});

test('list view image row renders preview thumbnail with soft-tinted fallback icon instead of an empty square', function () {
    $this->actingAs($this->user);

    $image = ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'cover.png',
        'mime_type' => 'image/png',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'guide.pdf',
        'mime_type' => 'application/pdf',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'notes.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);

    $html = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->call('setView', 'list')
        ->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $imageRow = $xpath->query('//tr[contains(., "cover.png")]')->item(0);
    expect($imageRow)->not->toBeNull();

    $imageRowHtml = $dom->saveHTML($imageRow);
    expect($imageRowHtml)->toContain(route('project.files.preview', [$this->project, $image]))
        ->and($imageRowHtml)->toContain('bg-brand-tint')
        ->and($xpath->query('.//svg', $xpath->query('.//td[2]', $imageRow)->item(0))->length)->toBeGreaterThan(0);

    expect($html)->toContain('bg-red-50')
        ->and($html)->toContain('bg-blue-50');
});

test('folder and file cards place selection checkbox next to the options menu in top-right without overlapping the icon and keep 44px touch targets', function () {
    $this->actingAs($this->user);

    Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Brand assets',
    ]);
    ProjectFile::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'logo.png',
        'mime_type' => 'image/png',
    ]);

    $html = Livewire::test('pages::projects.files.index', ['project' => $this->project])->html();

    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $folderArticle = $xpath->query('//article[contains(., "Brand assets")]')->item(0);
    expect($folderArticle)->not->toBeNull();
    $folderControls = $xpath->query('.//*[@data-card-controls]', $folderArticle)->item(0);
    expect($folderControls)->not->toBeNull()
        ->and($xpath->query('.//input[@type="checkbox"]', $folderControls)->length)->toBe(1)
        ->and($xpath->query('.//button', $folderControls)->length)->toBeGreaterThanOrEqual(1);

    $folderLabel = $xpath->query('.//label', $folderControls)->item(0);
    expect($folderLabel->getAttribute('class'))->toContain('max-md:min-h-11')
        ->and($folderLabel->getAttribute('class'))->toContain('max-md:min-w-11');

    $fileArticle = $xpath->query('//article[contains(., "logo.png")]')->item(0);
    expect($fileArticle)->not->toBeNull();
    $fileControls = $xpath->query('.//*[@data-card-controls]', $fileArticle)->item(0);
    expect($fileControls)->not->toBeNull()
        ->and($xpath->query('.//input[@type="checkbox"]', $fileControls)->length)->toBe(1)
        ->and($xpath->query('.//button', $fileControls)->length)->toBeGreaterThanOrEqual(1);

    $fileLabel = $xpath->query('.//label', $fileControls)->item(0);
    expect($fileLabel->getAttribute('class'))->toContain('max-md:min-h-11')
        ->and($fileLabel->getAttribute('class'))->toContain('max-md:min-w-11');
});

test('breadcrumbs are hidden at root and shown only when inside a folder', function () {
    $this->actingAs($this->user);

    $folder = Folder::factory()->create([
        'project_id' => $this->project->id,
        'created_by' => $this->user->id,
        'name' => 'Brand assets',
    ]);

    $component = Livewire::test('pages::projects.files.index', ['project' => $this->project])
        ->assertDontSeeHtml('data-files-breadcrumbs')
        ->call('navigateToFolder', $folder->id)
        ->assertSeeHtml('data-files-breadcrumbs')
        ->assertSee('Brand assets')
        ->call('navigateToRoot')
        ->assertDontSeeHtml('data-files-breadcrumbs');
});
