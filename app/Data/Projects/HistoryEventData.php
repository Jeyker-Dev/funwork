<?php

namespace App\Data\Projects;

use App\Enums\ActivityType;
use Carbon\CarbonImmutable;

readonly class HistoryEventData
{
    public function __construct(
        public ActivityType $type,
        public int $id,
        public string $actionVerb,
        public string $linkText,
        public string $url,
        public CarbonImmutable $occurredAt,
        public ?string $areaName = null,
        public ?string $areaColor = null,
        public bool $useWireNavigate = true,
    ) {}

    public function icon(): string
    {
        return match ($this->type) {
            ActivityType::Task => 'check',
            ActivityType::Decision => 'scale',
            ActivityType::Notebook => 'book-open',
            ActivityType::File => 'folder',
        };
    }

    public function iconCircleClasses(): string
    {
        return match ($this->type) {
            ActivityType::Task => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400',
            ActivityType::Decision => 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-400',
            ActivityType::Notebook => 'bg-blue-50 text-blue-600 dark:bg-blue-950/50 dark:text-blue-400',
            ActivityType::File => 'bg-orange-50 text-orange-600 dark:bg-orange-950/50 dark:text-orange-400',
        };
    }
}
