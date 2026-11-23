<?php

namespace App\Enums;

use App\Concerns\ResolvesNormalizedEnum;

enum AreaColor: string
{
    use ResolvesNormalizedEnum;

    case Blue = 'blue';
    case Green = 'green';
    case Amber = 'amber';
    case Purple = 'purple';
    case Red = 'red';
    case Cyan = 'cyan';
    case Pink = 'pink';
    case Zinc = 'zinc';

    public static function dotClassFor(?string $color): string
    {
        return match (self::resolve($color)) {
            self::Blue => 'bg-blue-500',
            self::Green => 'bg-emerald-500',
            self::Amber => 'bg-amber-500',
            self::Purple => 'bg-purple-500',
            self::Red => 'bg-red-500',
            self::Cyan => 'bg-cyan-500',
            self::Pink => 'bg-pink-500',
            self::Zinc, null => 'bg-zinc-400',
        };
    }

    public static function progressBarClassFor(?string $color): string
    {
        return self::dotClassFor($color);
    }

    public static function iconTintClassFor(?string $color): string
    {
        return match (self::resolve($color)) {
            self::Blue => 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
            self::Green => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
            self::Amber => 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
            self::Purple => 'bg-purple-500/10 text-purple-600 dark:text-purple-400',
            self::Red => 'bg-red-500/10 text-red-600 dark:text-red-400',
            self::Cyan => 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400',
            self::Pink => 'bg-pink-500/10 text-pink-600 dark:text-pink-400',
            self::Zinc, null => 'bg-surface-low text-ink-muted',
        };
    }
}
