<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\ListInboxItemsData;
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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_inbox_items')]
#[IsReadOnly]
#[Description('List inbox items captured in a FunWork project, newest first.')]
class ListInboxItemsTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to access this project.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the project.'),
        ];
    }

    protected function execute(Request $request, User $user): Response
    {
        $input = ListInboxItemsData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        Gate::forUser($user)->authorize('view', $project);

        $inboxItems = $project->inboxItems()
            ->where('user_id', $user->id)
            ->latest('created_at')
            ->latest('id')
            ->get()
            ->map(fn (InboxItem $inboxItem): array => McpPresenter::inboxItem($inboxItem))
            ->values()
            ->all();

        return Response::json([
            'inbox_items' => $inboxItems,
        ]);
    }
}
