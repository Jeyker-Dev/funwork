<?php

namespace App\Data\Projects;

use Illuminate\Support\Collection;

readonly class ProjectHistoryData
{
    /**
     * @param  Collection<int, HistoryWeekData>  $weeks
     */
    public function __construct(
        public Collection $weeks,
        public bool $hasMoreWeeks,
        public bool $hasAnyEvents,
    ) {}
}
