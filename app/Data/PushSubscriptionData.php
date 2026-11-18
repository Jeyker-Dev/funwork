<?php

namespace App\Data;

readonly class PushSubscriptionData
{
    public const int MAX_ENDPOINT_LENGTH = 500;

    public const int MAX_KEY_LENGTH = 255;

    public function __construct(
        public string $endpoint,
        public string $publicKey,
        public string $authToken,
        public ?string $contentEncoding = null,
    ) {}
}
