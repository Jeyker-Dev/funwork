<?php

namespace App\Actions\Projects;

use App\Data\Projects\ProjectInboxData;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\User;
use App\Support\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetProjectInboxDataAction
{
    private const int THIS_WEEK_LOOKBACK_DAYS = 6;

    /**
     * @var list<string>
     */
    private const array GROUP_ORDER = ['Today', 'This week', 'Older'];

    /**
     * Load project inbox items grouped by age in the user's timezone.
     */
    public function __invoke(Project $project, ?User $user = null): ProjectInboxData
    {
        $owner = $user ?? auth()->user();
        $timezone = DateHelper::userTimezone($owner);
        $localNow = CarbonImmutable::now($timezone);

        $items = $owner->inboxItems()
            ->where('project_id', $project->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $grouped = $items->groupBy(
            fn (InboxItem $item): string => $this->groupLabelFor(
                CarbonImmutable::instance($item->created_at)->setTimezone($timezone),
                $localNow,
            ),
        );

        $orderedGroups = collect(self::GROUP_ORDER)
            ->mapWithKeys(fn (string $label): array => [$label => $grouped->get($label, collect())])
            ->filter(fn (Collection $group): bool => $group->isNotEmpty())
            ->all();

        $oldestFirstItems = $items
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        return new ProjectInboxData(
            totalCount: $items->count(),
            groups: $orderedGroups,
            oldestFirstItems: $oldestFirstItems,
        );
    }

    private function groupLabelFor(CarbonImmutable $createdAtLocal, CarbonImmutable $localNow): string
    {
        $todayStart = $localNow->startOfDay();
        $weekStart = $localNow->subDays(self::THIS_WEEK_LOOKBACK_DAYS)->startOfDay();

        return match (true) {
            $createdAtLocal->greaterThanOrEqualTo($todayStart) => 'Today',
            $createdAtLocal->greaterThanOrEqualTo($weekStart) => 'This week',
            default => 'Older',
        };
    }
}
