<?php

namespace App\Data;

use App\Models\ApiToken;

readonly class CreatedApiTokenData
{
    public function __construct(
        public ApiToken $apiToken,
        public string $plainTextToken,
    ) {}
}
