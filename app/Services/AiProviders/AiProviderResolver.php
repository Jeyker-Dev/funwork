<?php

namespace App\Services\AiProviders;

use App\Contracts\AiProvider as AiProviderContract;
use App\Enums\AiProvider;
use App\Models\AiConnection;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the concrete AiProvider adapter for an AiConnection, or returns a
 * test fake when AiProvider is bound directly in the service container.
 */
final class AiProviderResolver
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function resolve(AiConnection $connection): AiProviderContract
    {
        if ($this->container->bound(AiProviderContract::class)) {
            return $this->container->make(AiProviderContract::class);
        }

        return match ($connection->provider) {
            AiProvider::Anthropic => $this->container->make(AnthropicProvider::class),
            default => $this->container->make(OpenAiCompatibleProvider::class),
        };
    }
}
