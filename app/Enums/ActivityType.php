<?php

namespace App\Enums;

enum ActivityType: string
{
    case Notebook = 'notebook';
    case File = 'file';
    case Decision = 'decision';
    case Task = 'task';

    public function icon(): string
    {
        return match ($this) {
            self::Notebook => 'book-open',
            self::File => 'document',
            self::Decision => 'scale',
            self::Task => 'check-circle',
        };
    }

    public function iconClasses(): string
    {
        return match ($this) {
            self::Notebook => 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
            self::File => 'bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400',
            self::Decision => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Task => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        };
    }

    public function actionLabel(): string
    {
        return match ($this) {
            self::Notebook => 'Edited notebook',
            self::File => 'Uploaded file',
            self::Decision => 'Logged decision',
            self::Task => 'Completed task',
        };
    }
}
