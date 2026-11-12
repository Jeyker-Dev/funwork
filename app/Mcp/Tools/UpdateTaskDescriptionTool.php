<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\UpdateTaskDescriptionData;
use App\Mcp\Support\McpPresenter;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('update_task_description')]
#[Description('Replace the description (Markdown brief) of an existing FunWork task.')]
class UpdateTaskDescriptionTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to update this task.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()
                ->required()
                ->description('ID of the task to update.'),
            'description' => $schema->string()
                ->required()
                ->max(Task::MAX_DESCRIPTION_LENGTH)
                ->description('New Markdown description for the task (max '.Task::MAX_DESCRIPTION_LENGTH.' characters).'),
        ];
    }

    protected function execute(Request $request, User $user): Response
    {
        $input = UpdateTaskDescriptionData::fromRequest($request);

        $task = Task::with(['project', 'area'])->findOrFail($input->taskId);

        Gate::forUser($user)->authorize('update', $task);

        $task->updateDescription($input->description);

        return Response::json(McpPresenter::taskDetail($task));
    }
}
