<?php

namespace App\Actions\Projects;

use App\Data\Projects\HistoryEventData;
use App\Data\Projects\HistoryWeekData;
use App\Data\Projects\ProjectHistoryData;
use App\Enums\ActivityType;
use App\Enums\DecisionStatus;
use App\Enums\TaskStatus;
use App\Models\Decision;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\Task;
use App\Models\User;
use App\Support\DateHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ListProjectHistoryAction
{
    public const int DEFAULT_WEEKS_LIMIT = 4;

    public const int WEEKS_STEP = 4;

    public function __invoke(
        Project $project,
        ?ActivityType $filter = null,
        int $weeksLimit = self::DEFAULT_WEEKS_LIMIT,
        ?User $user = null,
    ): ProjectHistoryData {
        $timezone = DateHelper::userTimezone($user);
        $nowLocal = CarbonImmutable::now($timezone);

        $events = $this->collectEvents($project, $filter, $timezone)
            ->sortByDesc(static fn (HistoryEventData $event): array => [$event->occurredAt->getTimestamp(), $event->id])
            ->values();

        $weekGroups = $events
            ->groupBy(static fn (HistoryEventData $event): string => $event->occurredAt->startOfWeek()->toDateString());

        $visibleWeeks = $weekGroups
            ->take($weeksLimit)
            ->map(fn (Collection $weekEvents, string $weekKey): HistoryWeekData => $this->buildWeekData($weekKey, $weekEvents, $nowLocal))
            ->values();

        $hasAnyEvents = $events->isNotEmpty() || ($filter !== null && $this->projectHasAnyEvents($project));

        return new ProjectHistoryData(
            weeks: $visibleWeeks,
            hasMoreWeeks: $weekGroups->count() > $weeksLimit,
            hasAnyEvents: $hasAnyEvents,
        );
    }

    /**
     * @return Collection<int, HistoryEventData>
     */
    private function collectEvents(Project $project, ?ActivityType $filter, string $timezone): Collection
    {
        $events = new Collection;

        if ($filter === null || $filter === ActivityType::Task) {
            $events = $events->merge($this->taskEvents($project, $timezone));
        }

        if ($filter === null || $filter === ActivityType::Decision) {
            $events = $events->merge($this->decisionEvents($project, $timezone));
        }

        if ($filter === null || $filter === ActivityType::Notebook) {
            $events = $events->merge($this->notebookEvents($project, $timezone));
        }

        if ($filter === null || $filter === ActivityType::File) {
            $events = $events->merge($this->fileEvents($project, $timezone));
        }

        return $events;
    }

    /**
     * @return Collection<int, HistoryEventData>
     */
    private function taskEvents(Project $project, string $timezone): Collection
    {
        return $project->tasks()
            ->with('area:id,name,color')
            ->where('status', TaskStatus::Done->value)
            ->whereNotNull('completed_at')
            ->get(['id', 'project_id', 'area_id', 'title', 'completed_at'])
            ->map(fn (Task $task): HistoryEventData => new HistoryEventData(
                type: ActivityType::Task,
                id: $task->id,
                actionVerb: 'Completed',
                linkText: $task->title,
                url: route('show.task', ['project' => $project, 'task' => $task]),
                occurredAt: CarbonImmutable::instance($task->completed_at)->setTimezone($timezone),
                areaName: $task->area?->name,
                areaColor: $task->area?->color,
            ));
    }

    /**
     * @return Collection<int, HistoryEventData>
     */
    private function decisionEvents(Project $project, string $timezone): Collection
    {
        return $project->decisions()
            ->where('status', DecisionStatus::Accepted->value)
            ->get(['id', 'project_id', 'number', 'title', 'decided_at', 'updated_at'])
            ->map(fn (Decision $decision): HistoryEventData => new HistoryEventData(
                type: ActivityType::Decision,
                id: $decision->id,
                actionVerb: 'Accepted decision',
                linkText: "#{$decision->number} {$decision->title}",
                url: route('project.decisions.show', ['project' => $project, 'decision' => $decision]),
                occurredAt: $this->resolveDecisionTimestamp($decision, $timezone),
            ));
    }

    /**
     * @return Collection<int, HistoryEventData>
     */
    private function notebookEvents(Project $project, string $timezone): Collection
    {
        return $project->notebooks()
            ->select(['id', 'project_id', 'name', 'created_at'])
            ->addSelect([
                'first_page_id' => NotebookPage::query()
                    ->select('id')
                    ->whereColumn('notebook_id', 'notebooks.id')
                    ->orderBy('order')
                    ->limit(1),
            ])
            ->get()
            ->map(function (Notebook $notebook) use ($project, $timezone): HistoryEventData {
                $firstPageId = $notebook->getAttribute('first_page_id');

                $url = $firstPageId !== null
                    ? route('project.notebooks.pages.edit', [
                        'project' => $project,
                        'notebook' => $notebook,
                        'page' => (int) $firstPageId,
                    ])
                    : route('project.notebooks', ['project' => $project]);

                return new HistoryEventData(
                    type: ActivityType::Notebook,
                    id: $notebook->id,
                    actionVerb: 'Created notebook',
                    linkText: $notebook->name,
                    url: $url,
                    occurredAt: CarbonImmutable::instance($notebook->created_at)->setTimezone($timezone),
                );
            });
    }

    /**
     * @return Collection<int, HistoryEventData>
     */
    private function fileEvents(Project $project, string $timezone): Collection
    {
        return $project->files()
            ->get(['id', 'project_id', 'name', 'created_at'])
            ->map(fn (ProjectFile $file): HistoryEventData => new HistoryEventData(
                type: ActivityType::File,
                id: $file->id,
                actionVerb: 'Uploaded file',
                linkText: $file->name,
                url: route('project.files.preview', ['project' => $project, 'projectFile' => $file]),
                occurredAt: CarbonImmutable::instance($file->created_at)->setTimezone($timezone),
                useWireNavigate: false,
            ));
    }

    private function resolveDecisionTimestamp(Decision $decision, string $timezone): CarbonImmutable
    {
        $updatedLocal = CarbonImmutable::instance($decision->updated_at)->setTimezone($timezone);

        if ($decision->decided_at === null) {
            return $updatedLocal;
        }

        $decidedDate = $decision->decided_at->toDateString();

        if ($updatedLocal->toDateString() === $decidedDate) {
            return $updatedLocal;
        }

        return CarbonImmutable::parse($decidedDate.' 12:00:00', $timezone);
    }

    /**
     * @param  Collection<int, HistoryEventData>  $weekEvents
     */
    private function buildWeekData(string $weekKey, Collection $weekEvents, CarbonImmutable $nowLocal): HistoryWeekData
    {
        $weekStart = CarbonImmutable::parse($weekKey, $nowLocal->getTimezone());
        $weekEnd = $weekStart->endOfWeek();
        $rangeLabel = $this->formatWeekRange($weekStart, $weekEnd, $nowLocal->year);

        $thisWeekStart = $nowLocal->startOfWeek()->toDateString();
        $lastWeekStart = $nowLocal->subWeek()->startOfWeek()->toDateString();

        [$title, $dateRange] = match ($weekKey) {
            $thisWeekStart => ['This week', $rangeLabel],
            $lastWeekStart => ['Last week', $rangeLabel],
            default => [$rangeLabel, null],
        };

        return new HistoryWeekData(
            weekKey: $weekKey,
            title: $title,
            dateRange: $dateRange,
            summary: $this->formatSummary($weekEvents),
            events: $weekEvents,
        );
    }

    private function formatWeekRange(CarbonImmutable $weekStart, CarbonImmutable $weekEnd, int $currentYear): string
    {
        if ($weekStart->year !== $weekEnd->year) {
            return $weekStart->format('M j, Y').'–'.$weekEnd->format('M j, Y');
        }

        $yearSuffix = $weekEnd->year !== $currentYear ? ', '.$weekEnd->year : '';

        if ($weekStart->month === $weekEnd->month) {
            return $weekStart->format('M j').'–'.$weekEnd->format('j').$yearSuffix;
        }

        return $weekStart->format('M j').'–'.$weekEnd->format('M j').$yearSuffix;
    }

    /**
     * @param  Collection<int, HistoryEventData>  $weekEvents
     */
    private function formatSummary(Collection $weekEvents): string
    {
        $counts = $weekEvents->countBy(static fn (HistoryEventData $event): string => $event->type->value);

        $parts = collect([
            [ActivityType::Task->value, 'task', ' done'],
            [ActivityType::Decision->value, 'decision', ''],
            [ActivityType::Notebook->value, 'notebook', ''],
            [ActivityType::File->value, 'file', ''],
        ])
            ->map(function (array $definition) use ($counts): ?string {
                [$typeValue, $singular, $suffix] = $definition;
                $count = (int) $counts->get($typeValue, 0);

                if ($count === 0) {
                    return null;
                }

                return $count.' '.Str::plural($singular, $count).$suffix;
            })
            ->filter()
            ->values();

        return $parts->implode(', ');
    }

    private function projectHasAnyEvents(Project $project): bool
    {
        return $project->tasks()->where('status', TaskStatus::Done->value)->whereNotNull('completed_at')->exists()
            || $project->decisions()->where('status', DecisionStatus::Accepted->value)->exists()
            || $project->notebooks()->exists()
            || $project->files()->exists();
    }
}
