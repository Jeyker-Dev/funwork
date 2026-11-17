<?php

namespace App\Mcp\Tools;

use App\Data\Mcp\ListDecisionsData;
use App\Enums\DecisionStatus;
use App\Mcp\Support\McpPresenter;
use App\Models\Decision;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_decisions')]
#[IsReadOnly]
#[Description('List Architecture Decision Records (ADRs) in a FunWork project, optionally filtered by status.')]
class ListDecisionsTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to access this project.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the project.'),
            'status' => $schema->string()
                ->enum(array_column(DecisionStatus::cases(), 'value'))
                ->description('Optional status filter: Proposed, Accepted, Rejected, or Superseded.'),
        ];
    }

    protected function execute(Request $request, User $user): Response
    {
        $input = ListDecisionsData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        Gate::forUser($user)->authorize('viewAny', [Decision::class, $project]);

        $query = $project->decisions()->orderBy('number');

        if ($input->status !== null) {
            $query->where('status', $input->status->value);
        }

        $decisions = $query->get()
            ->map(fn (Decision $decision): array => McpPresenter::decisionSummary($decision))
            ->values()
            ->all();

        return Response::json([
            'decisions' => $decisions,
        ]);
    }
}
