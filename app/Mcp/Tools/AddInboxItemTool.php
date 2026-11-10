<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\AddInboxItemData;
use App\Models\InboxItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add_inbox_item')]
#[Description('Capture a quick idea or note into a FunWork project\'s inbox.')]
class AddInboxItemTool extends Tool
{
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

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        $input = AddInboxItemData::fromRequest($request);

        $project = Project::find($input->projectId);

        if (! $project instanceof Project) {
            return Response::error('Project not found.');
        }

        try {
            Gate::forUser($user)->authorize('create', [InboxItem::class, $project]);
        } catch (AuthorizationException) {
            return Response::error('You are not authorized to add inbox items to this project.');
        }

        $inboxItem = InboxItem::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'content' => $input->content,
        ]);

        return Response::json([
            'id' => $inboxItem->id,
            'project_id' => $inboxItem->project_id,
            'content' => $inboxItem->content,
            'created_at' => $inboxItem->created_at?->toISOString(),
        ]);
    }
}
