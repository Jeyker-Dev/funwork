<?php

namespace App\Data\Projects;

use Illuminate\Support\Collection;

readonly class ProjectHealthData
{
    /**
     * @param  Collection<int, ProjectHealthCheckData>  $checks
     */
    public function __construct(
        public Collection $checks,
    ) {}

    public static function empty(): self
    {
        return new self(checks: collect());
    }

    public function isHealthy(): bool
    {
        return $this->checks->isEmpty();
    }

    public function count(): int
    {
        return $this->checks->count();
    }
}
