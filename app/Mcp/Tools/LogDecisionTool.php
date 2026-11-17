<?php

namespace App\Mcp\Tools;

use App\Actions\Decisions\CreateDecisionAction;
use App\Data\Mcp\LogDecisionData;
use App\Enums\DecisionStatus;
use App\Mcp\Support\McpPresenter;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('log_decision')]
#[Description('Record a new Architecture Decision Record (ADR) in a FunWork project.')]
class LogDecisionTool extends FunWorkTool
{
    protected string $authorizationMessage = 'You are not authorized to create decisions in this project.';

    public function __construct(
        private CreateDecisionAction $createDecision,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()
                ->required()
                ->description('ID of the target project.'),
            'title' => $schema->string()
                ->required()
                ->description('Decision title (max 255 characters).'),
            'context' => $schema->string()
                ->required()
                ->description('Problem context and forces in Markdown.'),
            'decision' => $schema->string()
                ->required()
                ->description('Chosen solution and rationale in Markdown.'),
            'consequences' => $schema->string()
                ->required()
                ->description('Trade-offs, benefits, and implications in Markdown.'),
            'status' => $schema->string()
                ->enum(array_column(DecisionStatus::loggableCases(), 'value'))
                ->default(DecisionStatus::Proposed->value)
                ->description('Initial decision status: Proposed, Accepted, or Rejected.'),
            'decided_at' => $schema->string()
                ->description('Optional decision date in Y-m-d format. Defaults to today when status is Accepted or Rejected, or null when Proposed.'),
        ];
    }

    protected function execute(Request $request, User $user): Response
    {
        $input = LogDecisionData::fromRequest($request);

        $project = Project::findOrFail($input->projectId);

        $decision = ($this->createDecision)($project, $user, $input->decision);

        return Response::json(McpPresenter::decisionDetail($decision));
    }
}
