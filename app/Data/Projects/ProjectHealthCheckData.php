<?php

namespace App\Data\Projects;

use App\Enums\HealthCheckSeverity;

readonly class ProjectHealthCheckData
{
    public function __construct(
        public string $key,
        public HealthCheckSeverity $severity,
        public string $title,
        public string $explanation,
        public int $count,
        public string $linkLabel,
        public string $linkUrl,
    ) {}
}
