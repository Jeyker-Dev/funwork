<?php

namespace App\Enums;

enum HealthCheckSeverity: string
{
    case Danger = 'danger';
    case Warning = 'warning';
    case Notice = 'notice';

    public function sortOrder(): int
    {
        return match ($this) {
            self::Danger => 1,
            self::Warning => 2,
            self::Notice => 3,
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Danger => 'exclamation-circle',
            self::Warning => 'exclamation-triangle',
            self::Notice => 'information-circle',
        };
    }

    public function iconClasses(): string
    {
        return match ($this) {
            self::Danger => 'bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400',
            self::Warning => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400',
            self::Notice => 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-400',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Danger => 'bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-400',
            self::Warning => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400',
            self::Notice => 'bg-surface-low text-ink-muted dark:bg-zinc-800 dark:text-zinc-300',
        };
    }
}
