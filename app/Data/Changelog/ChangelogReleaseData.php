<?php

namespace App\Data\Changelog;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

readonly class ChangelogReleaseData
{
    /**
     * @param  Collection<int, ChangelogEntryData>  $entries
     */
    public function __construct(
        public CarbonImmutable $date,
        public string $formattedDate,
        public bool $isNewSinceLastVisit,
        public Collection $entries,
    ) {}
}
