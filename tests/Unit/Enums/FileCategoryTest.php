<?php

use App\Enums\FileCategory;

test('fromMimeType maps mime types to categories', function (string $mimeType, FileCategory $category) {
    expect(FileCategory::fromMimeType($mimeType))->toBe($category);
})->with([
    'png image' => ['image/png', FileCategory::Image],
    'svg image' => ['image/svg+xml', FileCategory::Image],
    'pdf' => ['application/pdf', FileCategory::Pdf],
    'plain text' => ['text/plain', FileCategory::Document],
    'zip' => ['application/zip', FileCategory::Document],
]);

test('iconColor returns expected classes for each category', function () {
    expect(FileCategory::Image->iconColor())->toBe('text-brand')
        ->and(FileCategory::Pdf->iconColor())->toBe('text-red-500')
        ->and(FileCategory::Document->iconColor())->toBe('text-blue-500');
});

test('icon returns the flux icon name for each category', function () {
    expect(FileCategory::Image->icon())->toBe('photo')
        ->and(FileCategory::Pdf->icon())->toBe('document-text')
        ->and(FileCategory::Document->icon())->toBe('document');
});

test('previewClasses returns expected classes for each category', function () {
    expect(FileCategory::Image->previewClasses())->toBe('text-brand')
        ->and(FileCategory::Pdf->previewClasses())->toBe('text-red-500 bg-red-50 dark:bg-red-950/30 p-3 rounded-xl shadow-sm')
        ->and(FileCategory::Document->previewClasses())->toBe('text-blue-500 bg-blue-50 dark:bg-blue-950/30 p-3 rounded-xl shadow-sm');
});
