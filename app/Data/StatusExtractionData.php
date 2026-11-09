<?php

namespace App\Data;

use App\Enums\TaskStatus;

readonly class StatusExtractionData
{
    public function __construct(
        public TaskStatus $status,
        public string $remainder,
    ) {}
}
