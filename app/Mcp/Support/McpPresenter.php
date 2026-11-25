<?php

namespace App\Mcp\Support;

use App\Models\Area;
use App\Models\Decision;
use App\Models\InboxItem;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\Task;

class McpPresenter
{
    /**
     * @return array{id: int, name: string, slug: string}|null
     */
    public static function area(?Area $area): ?array
    {
        if (! $area instanceof Area) {
            return null;
        }

        return [
            'id' => $area->id,
            'name' => $area->name,
            'slug' => $area->slug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function project(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'type' => $project->type,
            'status' => $project->status,
            'archived_at' => $project->archived_at,
            'areas' => $project->areas->map(fn (Area $area): array => [
                'id' => $area->id,
                'name' => $area->name,
                'slug' => $area->slug,
                'color' => $area->color,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function notebook(Notebook $notebook): array
    {
        return [
            'id' => $notebook->id,
            'project_id' => $notebook->project_id,
            'name' => $notebook->name,
            'description' => $notebook->description,
            'area' => self::area($notebook->area),
            'created_at' => $notebook->created_at?->toISOString(),
            'updated_at' => $notebook->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function notebookPage(NotebookPage $page): array
    {
        $content = is_array($page->content) ? $page->content : [];

        return [
            'id' => $page->id,
            'notebook_id' => $page->notebook_id,
            'order' => $page->order,
            'title' => (string) ($content['title'] ?? ''),
            'body' => (string) ($content['body'] ?? ''),
            'created_at' => $page->created_at?->toISOString(),
            'updated_at' => $page->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inboxItem(InboxItem $item): array
    {
        return [
            'id' => $item->id,
            'project_id' => $item->project_id,
            'content' => $item->content,
            'created_at' => $item->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taskSummary(Task $task): array
    {
        return [
            'id' => $task->id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'status' => $task->status->value,
            'priority' => $task->priority->value,
            'area' => self::area($task->area),
            'due_date' => $task->due_date?->format('Y-m-d'),
            'position' => $task->position,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taskDetail(Task $task): array
    {
        return [
            ...self::taskSummary($task),
            'description' => $task->description,
            'created_at' => $task->created_at?->toISOString(),
            'updated_at' => $task->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function decisionSummary(Decision $decision): array
    {
        return [
            'id' => $decision->id,
            'project_id' => $decision->project_id,
            'code' => $decision->code,
            'title' => $decision->title,
            'status' => $decision->status->value,
            'decided_at' => $decision->decided_at?->format('Y-m-d'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function decisionDetail(Decision $decision): array
    {
        return [
            ...self::decisionSummary($decision),
            'context' => $decision->context,
            'decision' => $decision->decision,
            'consequences' => $decision->consequences,
            'created_at' => $decision->created_at?->toISOString(),
            'updated_at' => $decision->updated_at?->toISOString(),
        ];
    }
}
