<?php

namespace App\Data;

use App\Models\Area;

readonly class ResolvedAreaData
{
    public function __construct(
        public ?string $areaTag,
        public ?Area $area,
        public ?string $warning,
        public string $remainder,
    ) {}
}
