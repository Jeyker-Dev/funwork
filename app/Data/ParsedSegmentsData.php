<?php

namespace App\Data;

readonly class ParsedSegmentsData
{
    public function __construct(
        public string $title,
        public string $criterion,
        public ?string $dependency = null,
    ) {}
}
