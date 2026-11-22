<?php

use App\Enums\ActivityType;

test('icon returns the flux icon name for each activity type', function () {
    expect(ActivityType::Notebook->icon())->toBe('book-open')
        ->and(ActivityType::File->icon())->toBe('document')
        ->and(ActivityType::Decision->icon())->toBe('scale')
        ->and(ActivityType::Task->icon())->toBe('check-circle');
});

test('iconClasses returns expected classes for each activity type', function () {
    expect(ActivityType::Notebook->iconClasses())->toBe('bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400')
        ->and(ActivityType::File->iconClasses())->toBe('bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400')
        ->and(ActivityType::Decision->iconClasses())->toBe('bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400')
        ->and(ActivityType::Task->iconClasses())->toBe('bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400');
});

test('actionLabel returns the human verb phrase for each activity type', function () {
    expect(ActivityType::Notebook->actionLabel())->toBe('Edited notebook')
        ->and(ActivityType::File->actionLabel())->toBe('Uploaded file')
        ->and(ActivityType::Decision->actionLabel())->toBe('Logged decision')
        ->and(ActivityType::Task->actionLabel())->toBe('Completed task');
});
