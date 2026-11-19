<?php

use App\Enums\ActivityType;

test('icon returns the flux icon name for each activity type', function () {
    expect(ActivityType::Notebook->icon())->toBe('book-open')
        ->and(ActivityType::File->icon())->toBe('document');
});

test('iconClasses returns expected classes for each activity type', function () {
    expect(ActivityType::Notebook->iconClasses())->toBe('bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400')
        ->and(ActivityType::File->iconClasses())->toBe('bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400');
});
