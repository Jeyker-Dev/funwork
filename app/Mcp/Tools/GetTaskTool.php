<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\GetTaskData;
use App\Mcp\Support\McpPresenter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('get_task')]
#[Description('Get the full details of a single FunWork task by ID.')]
class GetTaskTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->required()
                ->description('ID of the task.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $input = GetTaskData::fromRequest($request);

        $task = Task::with(['project', 'area'])->find($input->taskId);

        if (! $task instanceof Task) {
            return Response::error('Task not found.');
        }

        try {
            Gate::forUser($user)->authorize('view', $task);
        } catch (AuthorizationException) {
            return Response::error('You are not authorized to view this task.');
        }

        return Response::json(McpPresenter::taskDetail($task));
    }
}
