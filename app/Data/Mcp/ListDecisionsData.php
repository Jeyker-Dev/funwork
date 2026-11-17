<?php

namespace App\Data\Mcp;

use App\Enums\DecisionStatus;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;

readonly class ListDecisionsData
{
    public function __construct(
        public int $projectId,
        public ?DecisionStatus $status,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'project_id' => ['required', 'integer'],
            'status' => ['nullable', 'string', Rule::enum(DecisionStatus::class)],
        ]);

        return new self(
            projectId: (int) $validated['project_id'],
            status: ! empty($validated['status']) ? DecisionStatus::from($validated['status']) : null,
        );
    }
}
