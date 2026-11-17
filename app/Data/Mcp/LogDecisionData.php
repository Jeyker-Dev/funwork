<?php

namespace App\Data\Mcp;

use App\Data\Decisions\DecisionData;
use App\Enums\DecisionStatus;
use App\Support\DateHelper;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

readonly class LogDecisionData
{
    public function __construct(
        public int $projectId,
        public DecisionData $decision,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'context' => ['required', 'string'],
            'decision' => ['required', 'string'],
            'consequences' => ['required', 'string'],
            'status' => ['nullable', 'string', Rule::in(array_column(DecisionStatus::loggableCases(), 'value'))],
            'decided_at' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $status = isset($validated['status'])
            ? DecisionStatus::from($validated['status'])
            : DecisionStatus::Proposed;

        $decidedAt = match (true) {
            filled($validated['decided_at'] ?? null) => Carbon::parse($validated['decided_at']),
            $status === DecisionStatus::Proposed => null,
            default => Carbon::parse(DateHelper::userToday($request->user())),
        };

        return new self(
            projectId: (int) $validated['project_id'],
            decision: new DecisionData(
                title: $validated['title'],
                status: $status,
                context: $validated['context'],
                decision: $validated['decision'],
                consequences: $validated['consequences'],
                decidedAt: $decidedAt,
            ),
        );
    }
}
