<?php

namespace App\Data\AiProviders;

use App\Enums\AiMessageRole;

readonly class AiChatMessage
{
    public function __construct(
        public AiMessageRole $role,
        public string $content,
    ) {}
}
