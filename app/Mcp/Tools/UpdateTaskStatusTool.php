<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\UpdateTaskStatusData;
use App\Enums\TaskStatus;
use App\Mcp\Support\McpPresenter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_task_status')]
#[Description('Update the status of a FunWork task (Todo, Doing, or Done).')]
class UpdateTaskStatusTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to update this task.';

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

    protected function execute(Request $request, User $user): Response
    {
        $input = UpdateTaskStatusData::fromRequest($request);

        $task = Task::with(['project', 'area'])->findOrFail($input->taskId);

        Gate::forUser($user)->authorize('update', $task);

        $task->changeStatus($input->status);

        return Response::json(McpPresenter::taskDetail($task));
    }
}
