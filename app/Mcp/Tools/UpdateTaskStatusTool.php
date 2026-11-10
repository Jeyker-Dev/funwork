<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\UpdateTaskStatusData;
use App\Enums\TaskStatus;
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

#[Name('update_task_status')]
#[Description('Update the status of a FunWork task (Todo, Doing, or Done).')]
class UpdateTaskStatusTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->required()
                ->description('ID of the task to update.'),
            'status' => $schema->string()
                ->enum(array_column(TaskStatus::cases(), 'value'))
                ->required()
                ->description('New status for the task: Todo, Doing, or Done.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $input = UpdateTaskStatusData::fromRequest($request);

        $task = Task::with('project')->find($input->taskId);

        if (! $task instanceof Task) {
            return Response::error('Task not found.');
        }

        try {
            Gate::forUser($user)->authorize('update', $task);
        } catch (AuthorizationException) {
            return Response::error('You are not authorized to update this task.');
        }

        $task->changeStatus($input->status);

        return Response::json([
            'id' => $task->id,
            'project_id' => $task->project_id,
            'title' => $task->title,
            'status' => $task->status->value,
            'updated_at' => $task->updated_at?->toISOString(),
        ]);
    }
}
