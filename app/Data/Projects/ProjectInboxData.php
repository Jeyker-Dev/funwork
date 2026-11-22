<?php

namespace App\Data\Projects;

use App\Models\InboxItem;
use Illuminate\Support\Collection;

readonly class ProjectInboxData
{
    /**
     * @param  array<string, Collection<int, InboxItem>>  $groups
     * @param  Collection<int, InboxItem>  $oldestFirstItems
     */
    public function __construct(
        public int $totalCount,
        public array $groups,
        public Collection $oldestFirstItems,
    ) {}

    public function isEmpty(): bool
    {
        return $this->totalCount === 0;
    }
}
