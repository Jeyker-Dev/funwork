<?php

namespace App\Data\AiConnections;

use App\Enums\AiProvider;

readonly class AiConnectionData
{
    public function __construct(
        public AiProvider $provider,
        public ?string $label = null,
        public ?string $baseUrl = null,
        public string $model = '',
        public ?string $apiKey = null,
        public bool $allowPaidModels = false,
    ) {}
}
