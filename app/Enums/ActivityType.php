<?php

namespace App\Enums;

enum ActivityType: string
{
    case Notebook = 'notebook';
    case File = 'file';

    public function icon(): string
    {
        return match ($this) {
            self::Notebook => 'book-open',
            self::File => 'document',
        };
    }

    public function iconClasses(): string
    {
        return match ($this) {
            self::Notebook => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
            self::File => 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
        };
    }
}
