<?php

use App\Actions\AiConversations\BuildAiChatMessagesAction;
use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Enums\TaskStatus;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Notebook;
use App\Models\NotebookPage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

it('builds system instructions first, rebuilds live project context on each call, keeps only Completed history oldest-to-newest, and places the current question last', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id, 'name' => 'Alpha Launch']);
    Task::factory()->create(['project_id' => $project->id, 'title' => 'Initial planning task', 'status' => TaskStatus::Todo]);

    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    $firstUser = AiMessage::factory()->create([
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
    // Failed and pending messages must never be included in history.
    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Failed,
        'content' => 'Failed answer text',
        'error' => 'Timed out',
    ]);
    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Pending,
        'content' => 'Pending placeholder',
    ]);

    $currentQuestion = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'What tasks exist now?',
    ]);

    $build = app(BuildAiChatMessagesAction::class);

    $firstCall = $build($conversation, $currentQuestion);

    expect($firstCall[0]->role)->toBe(AiMessageRole::System)
        ->and($firstCall[0]->content)
        ->toContain('only from the project context')
        ->toContain('Initial planning task')
        ->and(array_map(fn ($m) => [$m->role, $m->content], array_slice($firstCall, 1)))->toBe([
            [AiMessageRole::User, 'First question'],
            [AiMessageRole::Assistant, 'First answer'],
            [AiMessageRole::User, 'What tasks exist now?'],
        ]);

    // Mutate project data and rebuild: context reflects the latest project state.
    Task::factory()->create(['project_id' => $project->id, 'title' => 'Newly added migration task', 'status' => TaskStatus::Doing]);

    $secondCall = $build($conversation->fresh(), $currentQuestion);

    expect($secondCall[0]->content)->toContain('Newly added migration task');
});

it('drops oldest history messages first under the history cap while never dropping the current question', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    $chunkLength = intdiv(BuildAiChatMessagesAction::MAX_HISTORY_CHARS, 2) + 500;
    $oldestContent = 'OLDEST_'.str_repeat('a', $chunkLength);
    $recentContent = 'RECENT_'.str_repeat('b', $chunkLength);

    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => $oldestContent,
    ]);
    AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::Assistant,
        'status' => AiMessageStatus::Completed,
        'content' => $recentContent,
    ]);

    $currentQuestion = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'Current question must stay',
    ]);

    $messages = app(BuildAiChatMessagesAction::class)($conversation, $currentQuestion);
    $contents = array_map(fn ($message): string => $message->content, $messages);

    expect($contents)->not->toContain($oldestContent)
        ->toContain($recentContent)
        ->and($messages[array_key_last($messages)]->content)->toBe('Current question must stay');
});

it('truncates oversized project context and adds a partial-context note to instructions', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);

    $notebook = Notebook::factory()->create(['project_id' => $project->id, 'created_by' => $user->id]);
    $pagesCount = intdiv(BuildAiChatMessagesAction::MAX_CONTEXT_CHARS, 400) + 15;

    for ($i = 1; $i <= $pagesCount; $i++) {
        NotebookPage::factory()->create([
            'notebook_id' => $notebook->id,
            'created_by' => $user->id,
            'content' => [
                'title' => "Architecture Note {$i}",
                'body' => str_repeat("Detail {$i} ", 45),
            ],
        ]);
    }

    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
    ]);

    $currentQuestion = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'Summarize everything',
    ]);

    $messages = app(BuildAiChatMessagesAction::class)($conversation, $currentQuestion);

    expect($messages[0]->role)->toBe(AiMessageRole::System)
        ->and($messages[0]->content)->toContain('partial')
        ->and(mb_strlen($messages[0]->content))->toBeLessThan(BuildAiChatMessagesAction::MAX_CONTEXT_CHARS + 2_000);
});
