<?php

namespace App\Enums;

enum ChangelogEntryLabel: string
{
    case New = 'New';
    case Improved = 'Improved';
    case Fixed = 'Fixed';

    public function pillClasses(): string
    {
        return match ($this) {
            self::New => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
            self::Improved => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300',
            self::Fixed => 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
        };
    }
}
