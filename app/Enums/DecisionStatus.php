<?php

namespace App\Enums;

enum DecisionStatus: string
{
    case Proposed = 'Proposed';
    case Accepted = 'Accepted';
    case Rejected = 'Rejected';
    case Superseded = 'Superseded';

    public function fluxColor(): string
    {
        return match ($this) {
            self::Proposed => 'amber',
            self::Accepted => 'green',
            self::Rejected => 'red',
            self::Superseded => 'zinc',
        };
    }

    public function dotClass(): string
    {
        return match ($this) {
            self::Proposed => 'bg-amber-500',
            self::Accepted => 'bg-emerald-500',
            self::Rejected => 'bg-red-500',
            self::Superseded => 'bg-zinc-400 dark:bg-zinc-500',
        };
    }

    public function pillClasses(): string
    {
        return match ($this) {
            self::Proposed => 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300',
            self::Accepted => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300',
            self::Rejected => 'bg-danger-tint text-danger dark:bg-red-950/50 dark:text-red-300',
            self::Superseded => 'bg-surface-high text-ink-muted',
        };
    }

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Statuses allowed when creating a new decision via MCP.
     *
     * @return list<self>
     */
    public static function loggableCases(): array
    {
        return [
            self::Proposed,
            self::Accepted,
            self::Rejected,
        ];
    }
}
