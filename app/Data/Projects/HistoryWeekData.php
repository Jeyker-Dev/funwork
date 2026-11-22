<?php

namespace App\Data\Projects;

use Illuminate\Support\Collection;

readonly class HistoryWeekData
{
    /**
     * @param  Collection<int, HistoryEventData>  $events
     */
    public function __construct(
        public string $weekKey,
        public string $title,
        public ?string $dateRange,
        public string $summary,
        public Collection $events,
    ) {}
}
