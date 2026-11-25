<?php

namespace App\Data\Notebooks;

readonly class NotebookData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}
}
