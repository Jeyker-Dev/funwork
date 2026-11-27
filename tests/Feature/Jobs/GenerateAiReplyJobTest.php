<?php

use App\Data\AiProviders\AiReply;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Enums\AiProvider;
use App\Exceptions\AiConversations\MissingAiConnectionException;
use App\Exceptions\AiProviders\AiRateLimitedException;
use App\Jobs\GenerateAiReplyJob;
use App\Models\AiConnection;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\User;
use App\Services\AiProviders\FakeAiProvider;
use Illuminate\Support\Facades\Exceptions;

it('completes a pending assistant message with the provider text and model', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $connection = AiConnection::factory()->openRouter()->default()->create(['user_id' => $user->id]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'ai_connection_id' => $connection->id,
    ]);

    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'What is ADR-001 about?',
    ]);
    $assistantMessage = AiMessage::factory()->pending()->create([
        'ai_conversation_id' => $conversation->id,
    ]);

    $fake = FakeAiProvider::fake([
        new AiReply('ADR-001 defines authentication.', 'deepseek/deepseek-r1:free'),
    ]);

    GenerateAiReplyJob::dispatchSync($assistantMessage);

    $fresh = $assistantMessage->fresh();

    expect($fresh->status)->toBe(AiMessageStatus::Completed)
        ->and($fresh->content)->toBe('ADR-001 defines authentication.')
        ->and($fresh->model)->toBe('deepseek/deepseek-r1:free')
        ->and($fresh->error)->toBeNull()
        ->and($fake->callCount())->toBe(1);
});

it('marks the message Failed with the BusinessRuleException user message without reporting', function () {
    Exceptions::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $connection = AiConnection::factory()->openRouter()->default()->create([
        'user_id' => $user->id,
        'api_key' => 'sk-or-secret-never-logged-1111',
    ]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'ai_connection_id' => $connection->id,
    ]);

    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'Hello?',
    ]);
    $assistantMessage = AiMessage::factory()->pending()->create([
        'ai_conversation_id' => $conversation->id,
    ]);

    FakeAiProvider::fake([
        AiRateLimitedException::forOpenRouterFree(),
    ]);

    GenerateAiReplyJob::dispatchSync($assistantMessage);

    $fresh = $assistantMessage->fresh();

    expect($fresh->status)->toBe(AiMessageStatus::Failed)
        ->and($fresh->error)->toBe("You reached OpenRouter's usage limit. Try again later. It resets at midnight UTC.");

    Exceptions::assertNothingReported();
});

it('marks the message Failed with a generic message on unexpected Throwables and reports safely without leaking the API key', function () {
    Exceptions::fake();

    $secretKey = 'sk-ant-unexpected-leak-check-9999';
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $connection = AiConnection::factory()->default()->create([
        'user_id' => $user->id,
        'provider' => AiProvider::Anthropic,
        'api_key' => $secretKey,
    ]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'ai_connection_id' => $connection->id,
    ]);

    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'Hello?',
    ]);
    $assistantMessage = AiMessage::factory()->pending()->create([
        'ai_conversation_id' => $conversation->id,
    ]);

    FakeAiProvider::fake([
        new RuntimeException("Parser crash containing {$secretKey}"),
    ]);

    GenerateAiReplyJob::dispatchSync($assistantMessage);

    $fresh = $assistantMessage->fresh();

    expect($fresh->status)->toBe(AiMessageStatus::Failed)
        ->and($fresh->error)->toBe('Something went wrong. Try again.')
        ->and($fresh->error)->not->toContain($secretKey);

    Exceptions::assertReported(function (RuntimeException $reported) use ($secretKey): bool {
        return ! str_contains($reported->getMessage(), $secretKey);
    });
});

it('skips messages that are no longer pending', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $connection = AiConnection::factory()->default()->create(['user_id' => $user->id]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'ai_connection_id' => $connection->id,
    ]);

    $completed = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Completed,
        'content' => 'Already done.',
        'model' => 'gpt-5',
    ]);

    $fake = FakeAiProvider::fake();

    GenerateAiReplyJob::dispatchSync($completed);

    expect($fake->callCount())->toBe(0)
        ->and($completed->fresh()->content)->toBe('Already done.');
});

it('marks Failed when the user loses access to the project or has no AI connection left', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $connection = AiConnection::factory()->default()->create(['user_id' => $owner->id]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $owner->id,
        'project_id' => $project->id,
        'ai_connection_id' => $connection->id,
    ]);

    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'Question?',
    ]);
    $firstReply = AiMessage::factory()->pending()->create([
        'ai_conversation_id' => $conversation->id,
    ]);

    $fake = FakeAiProvider::fake();

    // Case 1: User loses access to project.
    $project->update(['owner_id' => $otherUser->id]);
    GenerateAiReplyJob::dispatchSync($firstReply);

    expect($fake->callCount())->toBe(0)
        ->and($firstReply->fresh()->status)->toBe(AiMessageStatus::Failed)
        ->and($firstReply->fresh()->error)->not->toBeEmpty();

    // Case 2: User regains access, but connection was deleted and no default remains.
    $project->update(['owner_id' => $owner->id]);
    $connection->delete();

    $secondReply = AiMessage::factory()->pending()->create([
        'ai_conversation_id' => $conversation->id,
    ]);

    GenerateAiReplyJob::dispatchSync($secondReply);

    expect($fake->callCount())->toBe(0)
        ->and($secondReply->fresh()->status)->toBe(AiMessageStatus::Failed)
        ->and($secondReply->fresh()->error)->toBe(MissingAiConnectionException::make()->getMessage());
});

it('falls back to the users current default connection when the conversation connection was deleted and targets the latest user question', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $oldConnection = AiConnection::factory()->create(['user_id' => $user->id, 'is_default' => false]);
    $fallbackConnection = AiConnection::factory()->default()->create(['user_id' => $user->id]);

    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'ai_connection_id' => $oldConnection->id,
    ]);

    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'First question',
    ]);
    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Completed,
        'content' => 'First answer',
    ]);
    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'Second question',
    ]);
    $secondPending = AiMessage::factory()->pending()->create([
        'ai_conversation_id' => $conversation->id,
    ]);

    $oldConnection->delete();

    $fake = FakeAiProvider::fake([
        new AiReply('Second answer', 'gpt-5'),
    ]);

    GenerateAiReplyJob::dispatchSync($secondPending);

    expect($secondPending->fresh()->status)->toBe(AiMessageStatus::Completed)
        ->and($secondPending->fresh()->content)->toBe('Second answer')
        ->and($fake->lastConnection()?->is($fallbackConnection))->toBeTrue()
        ->and($fake->lastMessages()[array_key_last($fake->lastMessages())]->content)->toBe('Second question');
});

it('marks a still-pending reply as Failed when the worker gives up on the job', function () {
    $assistantMessage = AiMessage::factory()->pending()->create();

    (new GenerateAiReplyJob($assistantMessage))->failed(new RuntimeException('Job timed out.'));

    expect($assistantMessage->fresh())
        ->status->toBe(AiMessageStatus::Failed)
        ->error->toBe('Something went wrong. Try again.');
});

it('leaves a completed reply untouched when the job failure arrives late', function () {
    $assistantMessage = AiMessage::factory()->create([
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Completed,
        'content' => 'Done.',
    ]);

    (new GenerateAiReplyJob($assistantMessage))->failed(new RuntimeException('Job timed out.'));

    expect($assistantMessage->fresh()->status)->toBe(AiMessageStatus::Completed);
});
