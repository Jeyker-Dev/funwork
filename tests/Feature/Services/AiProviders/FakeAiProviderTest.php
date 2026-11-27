<?php

use App\Data\AiProviders\AiChatMessage;
use App\Data\AiProviders\AiReply;
use App\Enums\AiMessageRole;
use App\Enums\AiProvider;
use App\Exceptions\AiProviders\AiRateLimitedException;
use App\Models\AiConnection;
use App\Services\AiProviders\FakeAiProvider;

it('returns queued AiReply values, throws queued exceptions, and records received messages', function (): void {
    $fake = new FakeAiProvider;
    $fake->pushReply(new AiReply('First answer', 'model-a'));
    $fake->pushException(AiRateLimitedException::forProvider(AiProvider::OpenAi));

    $connection = AiConnection::factory()->make(['provider' => AiProvider::OpenAi]);

    $firstMessages = [
        new AiChatMessage(AiMessageRole::System, 'System prompt'),
        new AiChatMessage(AiMessageRole::User, 'First question'),
    ];
    $secondMessages = [
        new AiChatMessage(AiMessageRole::User, 'Second question'),
    ];

    $reply = $fake->chat($connection, $firstMessages);

    expect($reply)->toBeInstanceOf(AiReply::class)
        ->and($reply->text)->toBe('First answer')
        ->and($reply->model)->toBe('model-a');

    expect(fn () => $fake->chat($connection, $secondMessages))
        ->toThrow(AiRateLimitedException::class);

    expect($fake->callCount())->toBe(2)
        ->and($fake->lastConnection())->toBe($connection)
        ->and($fake->lastMessages())->toBe($secondMessages)
        ->and($fake->recorded()[0]['messages'])->toBe($firstMessages);
});
