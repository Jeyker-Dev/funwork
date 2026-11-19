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
