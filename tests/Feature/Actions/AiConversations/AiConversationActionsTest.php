<?php

use App\Actions\AiConversations\DeleteAiConversationAction;
use App\Actions\AiConversations\RenameAiConversationAction;
use App\Actions\AiConversations\RetryAiReplyAction;
use App\Actions\AiConversations\SendAiMessageAction;
use App\Actions\AiConversations\StartAiConversationAction;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Exceptions\AiConversations\AiConversationTitleTooLongException;
use App\Exceptions\AiConversations\AiMessageTooLongException;
use App\Exceptions\AiConversations\AiRateLimitExceededException;
use App\Exceptions\AiConversations\AiReplyPendingException;
use App\Exceptions\AiConversations\CannotRetryAiReplyException;
use App\Exceptions\AiConversations\EmptyAiConversationTitleException;
use App\Exceptions\AiConversations\EmptyAiMessageException;
use App\Exceptions\AiConversations\MissingAiConnectionException;
use App\Jobs\GenerateAiReplyJob;
use App\Models\AiConnection;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

it('throws MissingAiConnectionException when starting a conversation with no AI connection', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    expect(fn () => app(StartAiConversationAction::class)($user, $project))
        ->toThrow(MissingAiConnectionException::class, 'Settings → Integrations');
});

it('starts a conversation using the users default AI connection', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $defaultConnection = AiConnection::factory()->default()->create(['user_id' => $user->id]);
    AiConnection::factory()->create(['user_id' => $user->id, 'is_default' => false]);

    $conversation = app(StartAiConversationAction::class)($user, $project);

    expect($conversation->user_id)->toBe($user->id)
        ->and($conversation->project_id)->toBe($project->id)
        ->and($conversation->ai_connection_id)->toBe($defaultConnection->id)
        ->and($conversation->title)->toBeNull();
});

it('sends a message, creates a pending assistant reply, sets title from first question only, and dispatches GenerateAiReplyJob', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    AiConnection::factory()->default()->create(['user_id' => $user->id]);
    $conversation = app(StartAiConversationAction::class)($user, $project);

    $pendingReply = app(SendAiMessageAction::class)($conversation, $user, '  What are the open tasks for authentication?  ');

    expect($pendingReply->role)->toBe(AiMessageRole::Assistant)
        ->and($pendingReply->status)->toBe(AiMessageStatus::Pending)
        ->and($conversation->fresh()->title)->toBe('What are the open tasks for authentication?')
        ->and($conversation->messages()->count())->toBe(2);

    $userMessage = $conversation->messages()->where('role', AiMessageRole::User->value)->firstOrFail();
    expect($userMessage->content)->toBe('What are the open tasks for authentication?')
        ->and($userMessage->status)->toBe(AiMessageStatus::Completed);

    Queue::assertPushed(
        GenerateAiReplyJob::class,
        fn (GenerateAiReplyJob $job): bool => $job->assistantMessage->is($pendingReply),
    );

    // Refuses a second message while reply is still pending.
    expect(fn () => app(SendAiMessageAction::class)($conversation->fresh(), $user, 'Another question?'))
        ->toThrow(AiReplyPendingException::class);

    // Once completed, sending another message does not overwrite the existing title.
    $pendingReply->markCompleted('Here are the tasks.', 'gpt-5');

    $secondPending = app(SendAiMessageAction::class)($conversation->fresh(), $user, 'And what about deadlines?');

    expect($secondPending->status)->toBe(AiMessageStatus::Pending)
        ->and($conversation->fresh()->title)->toBe('What are the open tasks for authentication?');
});

it('rejects empty and too-long messages with named exceptions', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    AiConnection::factory()->default()->create(['user_id' => $user->id]);
    $conversation = app(StartAiConversationAction::class)($user, $project);

    expect(fn () => app(SendAiMessageAction::class)($conversation, $user, '   '))
        ->toThrow(EmptyAiMessageException::class);

    $oversized = str_repeat('x', SendAiMessageAction::MAX_CONTENT_LENGTH + 1);

    expect(fn () => app(SendAiMessageAction::class)($conversation, $user, $oversized))
        ->toThrow(AiMessageTooLongException::class);

    Queue::assertNothingPushed();
});

it('allows retrying only the latest failed assistant message and dispatches the job again', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    $olderFailed = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Failed,
        'error' => 'Old failure',
    ]);

    $latestCompleted = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Completed,
        'content' => 'Succeeded later',
    ]);

    // Cannot retry an older failed message or a completed message.
    expect(fn () => app(RetryAiReplyAction::class)($olderFailed, $user))
        ->toThrow(CannotRetryAiReplyException::class);

    expect(fn () => app(RetryAiReplyAction::class)($latestCompleted, $user))
        ->toThrow(CannotRetryAiReplyException::class);

    $latestFailed = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Failed,
        'error' => 'Provider timed out.',
    ]);

    $retried = app(RetryAiReplyAction::class)($latestFailed, $user);

    expect($retried->fresh()->status)->toBe(AiMessageStatus::Pending)
        ->and($retried->fresh()->error)->toBeNull();

    Queue::assertPushed(
        GenerateAiReplyJob::class,
        fn (GenerateAiReplyJob $job): bool => $job->assistantMessage->is($latestFailed),
    );
});

it('enforces the per-user rate limit of 30 messages per hour', function () {
    Queue::fake();

    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    AiConnection::factory()->default()->create(['user_id' => $user->id]);
    $conversation = app(StartAiConversationAction::class)($user, $project);

    $limiter = RateLimiter::limiter(SendAiMessageAction::RATE_LIMITER_NAME);
    $limit = $limiter($user);
    RateLimiter::clear($limit->key);

    $send = app(SendAiMessageAction::class);

    for ($i = 1; $i <= SendAiMessageAction::MAX_MESSAGES_PER_HOUR; $i++) {
        $reply = $send($conversation, $user, "Question {$i}");
        $reply->markCompleted("Answer {$i}", 'gpt-5');
    }

    expect(fn () => $send($conversation, $user, 'Question 31'))
        ->toThrow(AiRateLimitExceededException::class, 'You sent too many messages. Try again in');
});

it('renames and deletes conversations with validation and authorization', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $owner->id,
        'project_id' => $project->id,
        'title' => 'Original',
    ]);

    expect(fn () => app(RenameAiConversationAction::class)($conversation, $owner, '   '))
        ->toThrow(EmptyAiConversationTitleException::class);

    expect(fn () => app(RenameAiConversationAction::class)($conversation, $owner, str_repeat('t', AiConversation::TITLE_MAX_LENGTH + 1)))
        ->toThrow(AiConversationTitleTooLongException::class);

    app(RenameAiConversationAction::class)($conversation, $owner, '  Sprint Planning Q&A  ');
    expect($conversation->fresh()->title)->toBe('Sprint Planning Q&A');

    expect(fn () => app(RenameAiConversationAction::class)($conversation, $intruder, 'Hacked'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(DeleteAiConversationAction::class)($conversation, $intruder))
        ->toThrow(AuthorizationException::class);

    app(DeleteAiConversationAction::class)($conversation, $owner);
    expect(AiConversation::find($conversation->id))->toBeNull();
});
