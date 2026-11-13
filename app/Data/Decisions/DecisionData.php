<?php

namespace App\Data\Decisions;

use App\Enums\DecisionStatus;
use Carbon\CarbonInterface;

readonly class DecisionData
{
    public function __construct(
        public string $title,
        public DecisionStatus $status,
        public string $context,
        public string $decision,
        public string $consequences,
        public ?CarbonInterface $decidedAt = null,
        public ?int $supersededById = null,
    ) {}
}
