<?php

use App\Enums\TaskStatus;

test('dot returns expected classes for each status', function () {
    expect(TaskStatus::Todo->dot())->toBe('bg-orange-500')
        ->and(TaskStatus::Doing->dot())->toBe('bg-yellow-500')
        ->and(TaskStatus::Done->dot())->toBe('bg-green-500');
});

test('columnBorder returns expected classes for each status', function () {
    expect(TaskStatus::Todo->columnBorder())->toBe('border-[rgba(255,128,64,0.48)]')
        ->and(TaskStatus::Doing->columnBorder())->toBe('border-[rgba(255,220,70,0.38)]')
        ->and(TaskStatus::Done->columnBorder())->toBe('border-[rgba(38,186,112,0.33)]');
});

test('columnBg returns expected classes for each status', function () {
    expect(TaskStatus::Todo->columnBg())->toBe('bg-[rgba(255,128,64,0.16)]')
        ->and(TaskStatus::Doing->columnBg())->toBe('bg-[rgba(255,220,70,0.17)]')
        ->and(TaskStatus::Done->columnBg())->toBe('bg-[rgba(38,186,112,0.13)]');
});

test('badge returns expected classes for each status', function () {
    expect(TaskStatus::Todo->badge())->toBe('border-orange-200 bg-orange-50 hover:bg-orange-200 text-orange-500')
        ->and(TaskStatus::Doing->badge())->toBe('border-yellow-200 bg-yellow-50 hover:bg-yellow-200 text-yellow-600')
        ->and(TaskStatus::Done->badge())->toBe('border-green-200 bg-green-50 hover:bg-green-200 text-green-600');
});

test('cardClasses returns expected classes for each status', function () {
    expect(TaskStatus::Todo->cardClasses())->toBe('border-[rgba(255,128,64,0.48)] bg-[rgba(255,128,64,0.23)] hover:bg-[rgba(255,128,64,0.33)]')
        ->and(TaskStatus::Doing->cardClasses())->toBe('border-[rgba(255,220,70,0.38)] bg-[rgba(255,220,70,0.17)] hover:bg-[rgba(255,220,70,0.27)]')
        ->and(TaskStatus::Done->cardClasses())->toBe('border-[rgba(38,186,112,0.33)] bg-[rgba(38,186,112,0.13)] hover:bg-[rgba(38,186,112,0.23)]');
});

test('text returns expected classes for each status', function () {
    expect(TaskStatus::Todo->text())->toBe('text-orange-500')
        ->and(TaskStatus::Doing->text())->toBe('text-yellow-500')
        ->and(TaskStatus::Done->text())->toBe('text-green-500');
});

test('accent returns expected classes for each status', function () {
    expect(TaskStatus::Todo->accent())->toBe('bg-orange-400')
        ->and(TaskStatus::Doing->accent())->toBe('bg-yellow-400')
        ->and(TaskStatus::Done->accent())->toBe('bg-green-400');
});

test('fluxColor returns expected literal color for each status', function () {
    expect(TaskStatus::Todo->fluxColor())->toBe('orange')
        ->and(TaskStatus::Doing->fluxColor())->toBe('yellow')
        ->and(TaskStatus::Done->fluxColor())->toBe('green');
});
