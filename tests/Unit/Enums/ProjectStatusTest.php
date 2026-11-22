<?php

use App\Enums\ProjectStatus;

test('resolve normalizes whitespace and casing for known statuses', function (?string $raw, ?ProjectStatus $expected) {
    expect(ProjectStatus::resolve($raw))->toBe($expected);
})->with([
    'null' => [null, null],
    'unknown' => ['paused', null],
    'trimmed uppercase active' => ['  ACTIVE ', ProjectStatus::Active],
    'planning' => ['Planning', ProjectStatus::Planning],
    'on-hold' => ['on-hold', ProjectStatus::OnHold],
    'done' => ['done', ProjectStatus::Done],
    'completed' => ['Completed', ProjectStatus::Completed],
]);

test('labelFor formats known statuses and falls back to headline or Unknown', function () {
    expect(ProjectStatus::labelFor('active'))->toBe('Active')
        ->and(ProjectStatus::labelFor('planning'))->toBe('Planning')
        ->and(ProjectStatus::labelFor('on-hold'))->toBe('On Hold')
        ->and(ProjectStatus::labelFor('done'))->toBe('Done')
        ->and(ProjectStatus::labelFor('completed'))->toBe('Done')
        ->and(ProjectStatus::labelFor('in_review'))->toBe('In Review')
        ->and(ProjectStatus::labelFor(''))->toBe('Unknown')
        ->and(ProjectStatus::labelFor(null))->toBe('Unknown');
});

test('dotClassFor and progressBarClassFor map known statuses and fall back cleanly', function () {
    expect(ProjectStatus::dotClassFor('active'))->toBe('bg-brand')
        ->and(ProjectStatus::dotClassFor('planning'))->toBe('bg-amber-500')
        ->and(ProjectStatus::dotClassFor('on-hold'))->toBe('bg-zinc-400')
        ->and(ProjectStatus::dotClassFor('done'))->toBe('bg-emerald-500')
        ->and(ProjectStatus::dotClassFor('unknown'))->toBe('bg-zinc-400')
        ->and(ProjectStatus::progressBarClassFor('active'))->toBe('bg-brand')
        ->and(ProjectStatus::progressBarClassFor('planning'))->toBe('bg-amber-500')
        ->and(ProjectStatus::progressBarClassFor('on-hold'))->toBe('bg-zinc-400')
        ->and(ProjectStatus::progressBarClassFor('completed'))->toBe('bg-emerald-500')
        ->and(ProjectStatus::progressBarClassFor(null))->toBe('bg-brand');
});
