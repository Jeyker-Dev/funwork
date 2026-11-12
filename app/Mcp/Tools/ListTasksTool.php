<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\ListTasksData;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpPresenter;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('list_tasks')]
#[Description('List tasks in a FunWork project, optionally filtered by status (Todo, Doing, Done) and area slug.')]
class ListTasksTool extends FunWorkTool
{
    public const NO_AREA_FILTER = 'none';

    protected string $authorizationMessage = 'You are not authorized to access this project.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the project.'),
            'status' => $schema->string()
                ->enum(array_column(TaskStatus::cases(), 'value'))
                ->description('Optional task status filter: Todo, Doing, or Done.'),
            'area' => $schema->string()
                ->description('Optional area slug (e.g. backend, frontend) or "none" for tasks without an area.'),
        ];
    }

    protected function execute(Request $request, User $user): Response
    {
        $input = ListTasksData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        Gate::forUser($user)->authorize('view', $project);

        $query = $project->tasks()->with('area')->orderBy('position')->orderBy('id');

        if ($input->status !== null) {
            $query->where('status', $input->status->value);
        }

        if ($input->area !== null) {
            if (strtolower($input->area) === self::NO_AREA_FILTER) {
                $query->whereNull('area_id');
            } else {
                $area = $project->areaByKey($input->area);

                if ($area === null) {
                    return Response::error("Area [{$input->area}] not found in this project.");
                }

                $query->where('area_id', $area->id);
            }
        }

        $tasks = $query->get()
            ->map(fn (Task $task): array => McpPresenter::taskSummary($task))
            ->values()
            ->all();

        return Response::json([
            'tasks' => $tasks,
        ]);
    }
}
