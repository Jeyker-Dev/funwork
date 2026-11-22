<?php

namespace App\Enums;

use App\Concerns\ResolvesNormalizedEnum;

enum ProjectType: string
{
    use ResolvesNormalizedEnum;

    case Programming = 'programming';
    case Study = 'study';
    case Music = 'music';
    case Design = 'design';
    case Product = 'product';
    case Docs = 'docs';
    case Personal = 'personal';

    public static function badgeClassesFor(?string $type): string
    {
        return match (self::resolve($type)) {
            self::Programming => 'bg-blue-50 text-blue-700 border-blue-200/60 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800/50',
            self::Study => 'bg-purple-50 text-purple-700 border-purple-200/60 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800/50',
            self::Music => 'bg-emerald-50 text-emerald-700 border-emerald-200/60 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/50',
            self::Design => 'bg-teal-50 text-teal-700 border-teal-200/60 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-800/50',
            self::Product => 'bg-indigo-50 text-indigo-700 border-indigo-200/60 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800/50',
            self::Docs => 'bg-sky-50 text-sky-700 border-sky-200/60 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-800/50',
            self::Personal, null => 'bg-surface-low text-ink-muted border-ghost',
        };
    }
}
