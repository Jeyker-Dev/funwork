<?php

namespace App\Data\Changelog;

use App\Enums\ChangelogEntryLabel;

readonly class ChangelogEntryData
{
    public function __construct(
        public ChangelogEntryLabel $label,
        public string $title,
        public string $description,
        public ?string $url = null,
        public ?string $linkLabel = null,
    ) {}
}
