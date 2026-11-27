<?php

namespace App\Data\AiProviders;

readonly class AiReply
{
    public function __construct(
        public string $text,
        public string $model,
    ) {}
}
