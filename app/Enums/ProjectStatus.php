<?php

namespace App\Enums;

use App\Concerns\ResolvesNormalizedEnum;
use Illuminate\Support\Str;

enum ProjectStatus: string
{
    use ResolvesNormalizedEnum;

    case Active = 'active';
    case Planning = 'planning';
    case OnHold = 'on-hold';
    case Done = 'done';
    case Completed = 'completed';

    public function isOpen(): bool
    {
        return match ($this) {
            self::Active, self::Planning, self::OnHold => true,
            self::Done, self::Completed => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $status): bool => $status->isOpen())
            ->map(fn (self $status): string => $status->value)
            ->values()
            ->all();
    }

    public static function labelFor(?string $status): string
    {
        return match (self::resolve($status)) {
            self::Active => 'Active',
            self::Planning => 'Planning',
            self::OnHold => 'On Hold',
            self::Done, self::Completed => 'Done',
            null => filled($status) ? Str::headline($status) : 'Unknown',
        };
    }

    public static function dotClassFor(?string $status): string
    {
        return match (self::resolve($status)) {
            self::Active => 'bg-brand',
            self::Planning => 'bg-amber-500',
            self::OnHold => 'bg-zinc-400',
            self::Done, self::Completed => 'bg-emerald-500',
            null => 'bg-zinc-400',
        };
    }

    public static function progressBarClassFor(?string $status): string
    {
        return match (self::resolve($status)) {
            self::Active => 'bg-brand',
            self::Planning => 'bg-amber-500',
            self::OnHold => 'bg-zinc-400',
            self::Done, self::Completed => 'bg-emerald-500',
            null => 'bg-brand',
        };
    }
}
