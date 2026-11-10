<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\ListProjectsData;
use App\Mcp\Support\McpPresenter;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('list_projects')]
#[Description('List the authenticated user\'s FunWork projects and their configured areas.')]
class ListProjectsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'include_archived' => $schema->boolean()
                ->default(false)
                ->description('Whether to include archived projects.'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Unauthenticated.');
        }

        Gate::forUser($user)->authorize('viewAny', Project::class);

        $input = ListProjectsData::fromRequest($request);

        $query = $user->projects()->with('areas')->orderBy('id');

        if (! $input->includeArchived) {
            $query->whereNull('archived_at');
        }

        $projects = $query->get()
            ->map(fn (Project $project): array => McpPresenter::project($project))
            ->values()
            ->all();

        return Response::json([
            'projects' => $projects,
        ]);
    }
}
