<?php

namespace App\Actions\Projects;

use App\Enums\DecisionStatus;
use App\Enums\TaskStatus;
use App\Models\Area;
use App\Models\Decision;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Builds a concise Markdown brief of a project for AI consumption or clipboard export.
 *
 * It does not authorize: callers verify project access first.
 */
class BuildProjectContextAction
{
    public const int MAX_NOTEBOOK_PAGE_BODY_LENGTH = 500;

    private const string NO_AREA_LABEL = 'No area';

    private const string EMPTY_PROJECT_MESSAGE = 'This project is empty.';

    private const int UNASSIGNED_AREA_SORT_ORDER = PHP_INT_MAX;

    public function __invoke(Project $project, User $user): string
    {
        $areas = $project->areas()->orderBy('position')->orderBy('id')->get();
        $openTasks = $project->tasks()->with('area')->open()->orderBy('position')->orderBy('id')->get();
        $decisions = $project->decisions()->with('supersededBy')->orderBy('number')->get();
        $notebooks = $project->notebooks()
            ->where('is_archived', false)
            ->with([
                'area',
                'pages' => fn (Builder $query): Builder => $query->orderBy('order')->orderBy('id'),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
        $inboxItems = $project->inboxItems()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->latest('id')
            ->get();

        $contentSections = array_values(array_filter([
            $this->formatAreasSection($areas),
            $this->formatOpenTasksSection($openTasks),
            $this->formatDecisionsSection($decisions),
            $this->formatNotebooksSection($notebooks),
            $this->formatInboxSection($inboxItems),
        ]));

        $sections = [
            $this->formatProjectSection($project),
            ...($contentSections !== [] ? $contentSections : [self::EMPTY_PROJECT_MESSAGE]),
        ];

        return implode("\n\n", $sections)."\n";
    }

    private function formatProjectSection(Project $project): string
    {
        $lines = [
            "# {$project->name}",
            '',
            "- Type: {$project->type}",
            "- Status: {$project->status}",
        ];

        if (filled($project->description)) {
            $lines[] = '';
            $lines[] = trim((string) $project->description);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Area>  $areas
     */
    private function formatAreasSection(Collection $areas): ?string
    {
        if ($areas->isEmpty()) {
            return null;
        }

        $lines = $areas
            ->map(fn (Area $area): string => "- {$area->name}")
            ->all();

        return implode("\n", ['## Areas', '', ...$lines]);
    }

    /**
     * @param  Collection<int, Task>  $openTasks
     */
    private function formatOpenTasksSection(Collection $openTasks): ?string
    {
        if ($openTasks->isEmpty()) {
            return null;
        }

        $blocks = ['## Open Tasks'];

        foreach ([TaskStatus::Todo, TaskStatus::Doing] as $status) {
            $tasksForStatus = $openTasks
                ->filter(fn (Task $task): bool => $task->status === $status)
                ->values();

            if ($tasksForStatus->isEmpty()) {
                continue;
            }

            $blocks[] = "### {$status->value}";

            $groupedByArea = $tasksForStatus
                ->groupBy(fn (Task $task): int => $task->area_id ?? 0)
                ->sortBy(function (Collection $group): array {
                    /** @var Task $firstTask */
                    $firstTask = $group->first();
                    $area = $firstTask->area;

                    if (! $area instanceof Area) {
                        return [self::UNASSIGNED_AREA_SORT_ORDER, self::UNASSIGNED_AREA_SORT_ORDER];
                    }

                    return [$area->position, $area->id];
                });

            foreach ($groupedByArea as $areaTasks) {
                /** @var Task $firstTask */
                $firstTask = $areaTasks->first();
                $areaName = $firstTask->area?->name ?? self::NO_AREA_LABEL;

                $taskLines = $areaTasks
                    ->map(fn (Task $task): string => $this->formatTaskLine($task))
                    ->all();

                $blocks[] = implode("\n", ["#### {$areaName}", '', ...$taskLines]);
            }
        }

        return implode("\n\n", $blocks);
    }

    private function formatTaskLine(Task $task): string
    {
        $details = [
            "Priority: {$task->priority->value}",
        ];

        if ($task->due_date !== null) {
            $details[] = 'Due: '.$task->due_date->format('Y-m-d');
        }

        $details[] = "ID: {$task->id}";

        return sprintf('- %s (%s)', $task->title, implode(', ', $details));
    }

    /**
     * @param  Collection<int, Decision>  $decisions
     */
    private function formatDecisionsSection(Collection $decisions): ?string
    {
        if ($decisions->isEmpty()) {
            return null;
        }

        $fullDecisions = $decisions->filter(
            fn (Decision $decision): bool => in_array($decision->status, [DecisionStatus::Accepted, DecisionStatus::Proposed], true)
        );
        $summaryDecisions = $decisions->filter(
            fn (Decision $decision): bool => in_array($decision->status, [DecisionStatus::Superseded, DecisionStatus::Rejected], true)
        );

        $blocks = ['## Decisions'];

        foreach ($fullDecisions as $decision) {
            $blocks[] = $this->formatFullDecision($decision);
        }

        if ($summaryDecisions->isNotEmpty()) {
            $summaryLines = $summaryDecisions
                ->map(fn (Decision $decision): string => $this->formatSummaryDecisionLine($decision))
                ->all();

            if ($fullDecisions->isNotEmpty()) {
                $blocks[] = implode("\n", ['### Superseded & Rejected', '', ...$summaryLines]);
            } else {
                $blocks[] = implode("\n", $summaryLines);
            }
        }

        return implode("\n\n", $blocks);
    }

    private function formatFullDecision(Decision $decision): string
    {
        return implode("\n\n", [
            "### {$decision->code} {$decision->title}",
            "Status: {$decision->status->value}",
            "#### Context\n\n".trim($decision->context),
            "#### Decision\n\n".trim($decision->decision),
            "#### Consequences\n\n".trim($decision->consequences),
        ]);
    }

    private function formatSummaryDecisionLine(Decision $decision): string
    {
        if ($decision->status === DecisionStatus::Superseded) {
            $replacement = $decision->supersededBy?->code;
            $suffix = $replacement !== null
                ? " (superseded by {$replacement})"
                : ' (superseded)';

            return "- {$decision->code} {$decision->title}{$suffix}";
        }

        return "- {$decision->code} {$decision->title} ({$decision->status->value})";
    }

    /**
     * @param  Collection<int, Notebook>  $notebooks
     */
    private function formatNotebooksSection(Collection $notebooks): ?string
    {
        if ($notebooks->isEmpty()) {
            return null;
        }

        $blocks = ['## Notebooks'];

        foreach ($notebooks as $notebook) {
            $notebookLines = ["### {$notebook->name}"];

            if ($notebook->area instanceof Area) {
                $notebookLines[] = '';
                $notebookLines[] = "Area: {$notebook->area->name}";
            }

            if ($notebook->pages->isEmpty()) {
                $notebookLines[] = '';
                $notebookLines[] = '_No pages._';
                $blocks[] = implode("\n", $notebookLines);

                continue;
            }

            foreach ($notebook->pages->values() as $index => $page) {
                $notebookLines[] = '';
                $notebookLines[] = $this->formatNotebookPage($page, $index);
            }

            $blocks[] = implode("\n", $notebookLines);
        }

        return implode("\n\n", $blocks);
    }

    private function formatNotebookPage(NotebookPage $page, int $index): string
    {
        $content = is_array($page->content) ? $page->content : [];
        $title = filled($content['title'] ?? null)
            ? trim((string) $content['title'])
            : 'Page '.($index + 1);
        $body = trim((string) ($content['body'] ?? ''));

        if ($body === '') {
            return "#### {$title}";
        }

        $truncatedBody = Str::limit($body, self::MAX_NOTEBOOK_PAGE_BODY_LENGTH);

        return "#### {$title}\n\n{$truncatedBody}";
    }

    /**
     * @param  Collection<int, InboxItem>  $inboxItems
     */
    private function formatInboxSection(Collection $inboxItems): ?string
    {
        if ($inboxItems->isEmpty()) {
            return null;
        }

        $lines = $inboxItems
            ->map(fn (InboxItem $item): string => '- '.trim($item->content))
            ->all();

        return implode("\n", ['## Inbox', '', ...$lines]);
    }
}
