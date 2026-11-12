<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\AddInboxItemData;
use App\Mcp\Support\McpPresenter;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('add_inbox_item')]
#[Description('Capture a quick idea or note into a FunWork project\'s inbox.')]
class AddInboxItemTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to add inbox items to this project.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the project to capture the inbox item in.'),
            'content' => $schema->string()
                ->required()
                ->description('Content of the inbox item (max 1000 characters).'),
        ];
    }

    protected function execute(Request $request, User $user): Response
    {
        $input = AddInboxItemData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        Gate::forUser($user)->authorize('create', [InboxItem::class, $project]);

        $inboxItem = InboxItem::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'content' => $input->content,
        ]);

        return Response::json(McpPresenter::inboxItem($inboxItem));
    }
}
