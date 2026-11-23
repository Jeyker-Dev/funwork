<?php

use App\Enums\FileCategory;
use App\Models\Folder;
use App\Models\ProjectFile;

test('category is derived from the mime type', function () {
    $file = new ProjectFile(['mime_type' => 'application/pdf']);

    expect($file->category)->toBe(FileCategory::Pdf);
});

test('extensionLabel returns the mime subtype', function () {
    $file = new ProjectFile(['mime_type' => 'image/png']);

    expect($file->extensionLabel)->toBe('png');
});

test('extensionLabel falls back when the mime type has no subtype', function () {
    $file = new ProjectFile(['mime_type' => 'unknown']);

    expect($file->extensionLabel)->toBe('File');
});

test('sizeInMegabytes formats the size with two decimals', function () {
    $file = new ProjectFile(['size' => 1572864]);

    expect($file->sizeInMegabytes)->toBe('1.50');
});

test('folderPath joins the folder breadcrumb names', function () {
    $parent = (new Folder(['name' => 'Design']))->setRelation('parent', null);
    $folder = (new Folder(['name' => 'Logos']))->setRelation('parent', $parent);
    $file = (new ProjectFile)->setRelation('folder', $folder);

    expect($file->folderPath)->toBe('Design / Logos');
});

test('folderPath is null when the file has no folder', function () {
    $file = (new ProjectFile)->setRelation('folder', null);

    expect($file->folderPath)->toBeNull();
});

test('typeLabel returns human readable real file types', function (string $mimeType, string $name, string $expected) {
    $file = new ProjectFile(['mime_type' => $mimeType, 'name' => $name]);

    expect($file->typeLabel)->toBe($expected);
})->with([
    'pdf' => ['application/pdf', 'brief.pdf', 'PDF'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'spec.docx', 'Word document'],
    'doc' => ['application/msword', 'legacy.doc', 'Word document'],
    'png image' => ['image/png', 'hero.png', 'Image'],
    'svg image' => ['image/svg+xml', 'logo.svg', 'Image'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'budget.xlsx', 'Spreadsheet'],
    'csv' => ['text/csv', 'export.csv', 'CSV'],
    'plain text' => ['text/plain', 'notes.txt', 'Text'],
    'markdown' => ['text/markdown', 'readme.md', 'Markdown'],
    'zip' => ['application/zip', 'bundle.zip', 'ZIP archive'],
    'unknown' => ['application/octet-stream', 'data.bin', 'Document'],
]);

test('formattedSize formats bytes, kilobytes, and megabytes', function (int $bytes, string $expected) {
    $file = new ProjectFile(['size' => $bytes]);

    expect($file->formattedSize)->toBe($expected);
})->with([
    'bytes' => [512, '512 B'],
    'kilobytes' => [2048, '2 KB'],
    'megabytes' => [1572864, '1.50 MB'],
]);
