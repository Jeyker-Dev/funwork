<?php

use App\Enums\ProjectType;

test('resolve normalizes whitespace and casing for known project types', function (?string $raw, ?ProjectType $expected) {
    expect(ProjectType::resolve($raw))->toBe($expected);
})->with([
    'null' => [null, null],
    'unknown' => ['marketing', null],
    'trimmed programming' => ['  Programming ', ProjectType::Programming],
    'study' => ['STUDY', ProjectType::Study],
    'music' => ['music', ProjectType::Music],
    'design' => ['Design', ProjectType::Design],
    'product' => ['Product', ProjectType::Product],
    'docs' => ['Docs', ProjectType::Docs],
    'personal' => ['personal', ProjectType::Personal],
]);

test('badgeClassesFor omits orange and brand classes for every project type', function (?string $type) {
    $classes = ProjectType::badgeClassesFor($type);

    expect($classes)
        ->not->toBeEmpty()
        ->not->toContain('orange')
        ->not->toContain('brand');
})->with([
    'programming' => ['Programming'],
    'study' => ['Study'],
    'music' => ['Music'],
    'design' => ['Design'],
    'product' => ['Product'],
    'docs' => ['Docs'],
    'personal' => ['personal'],
    'unknown fallback' => ['other-type'],
    'null fallback' => [null],
]);

test('badgeClassesFor assigns distinct classes to each known project type', function () {
    $styles = collect(ProjectType::cases())
        ->map(fn (ProjectType $type): string => ProjectType::badgeClassesFor($type->value))
        ->unique();

    expect($styles)->toHaveCount(count(ProjectType::cases()));
});

test('badgeClassesFor falls back to neutral surface styling for unknown types', function () {
    expect(ProjectType::badgeClassesFor('custom-type'))
        ->toBe(ProjectType::badgeClassesFor(null))
        ->toContain('bg-surface-low');
});
