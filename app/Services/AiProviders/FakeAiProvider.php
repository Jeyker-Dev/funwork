<?php

namespace App\Services\AiProviders;

use App\Contracts\AiProvider as AiProviderContract;
use App\Data\AiProviders\AiChatMessage;
use App\Data\AiProviders\AiReply;
use App\Models\AiConnection;
use Throwable;

/**
 * In-memory test double for AiProvider that queues replies or exceptions
 * and records received messages for assertions.
 */
final class FakeAiProvider implements AiProviderContract
{
    /**
     * @var list<AiReply|Throwable>
     */
    private array $queue = [];

    /**
     * @var list<array{connection: AiConnection, messages: list<AiChatMessage>}>
     */
    private array $recorded = [];

    /**
     * Registers a FakeAiProvider instance in the container so AiProviderResolver returns it.
     *
     * @param  list<AiReply|Throwable>  $responses
     */
    public static function fake(array $responses = []): self
    {
        $instance = new self;

        foreach ($responses as $response) {
            if ($response instanceof Throwable) {
                $instance->pushException($response);
            } else {
                $instance->pushReply($response);
            }
        }

        app()->instance(AiProviderContract::class, $instance);

        return $instance;
    }

    public function pushReply(AiReply|string $reply, string $model = 'fake-model'): self
    {
        $this->queue[] = $reply instanceof AiReply
            ? $reply
            : new AiReply($reply, $model);

        return $this;
    }

    public function pushException(Throwable $exception): self
    {
        $this->queue[] = $exception;

        return $this;
    }

    public function chat(AiConnection $connection, array $messages): AiReply
    {
        $this->recorded[] = [
            'connection' => $connection,
            'messages' => array_values($messages),
        ];

        $next = array_shift($this->queue);

        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next ?? new AiReply('Fake AI response.', $connection->model);
    }

    /**
     * @return list<array{connection: AiConnection, messages: list<AiChatMessage>}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    /**
     * @return list<AiChatMessage>
     */
    public function lastMessages(): array
    {
        if ($this->recorded === []) {
            return [];
        }

        return $this->recorded[array_key_last($this->recorded)]['messages'];
    }

    public function lastConnection(): ?AiConnection
    {
        if ($this->recorded === []) {
            return null;
        }

        return $this->recorded[array_key_last($this->recorded)]['connection'];
    }

    public function callCount(): int
    {
        return count($this->recorded);
    }
}
