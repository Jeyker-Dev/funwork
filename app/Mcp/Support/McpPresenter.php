<?php

namespace App\Mcp\Support;

use App\Models\Area;
use App\Models\InboxItem;
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
}
