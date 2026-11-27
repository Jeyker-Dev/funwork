<?php

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use App\Models\AiConnection;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Project;
use App\Models\User;

it('defines User, Project, AiConnection, and AiConversation relationships', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $connection = AiConnection::factory()->default()->create(['user_id' => $user->id]);

    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'ai_connection_id' => $connection->id,
        'title' => 'Architecture questions',
    ]);

    $message = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
        'role' => AiMessageRole::User,
        'status' => AiMessageStatus::Completed,
        'content' => 'What ADRs are accepted?',
    ]);

    expect($conversation->user->is($user))->toBeTrue()
        ->and($conversation->project->is($project))->toBeTrue()
        ->and($conversation->aiConnection->is($connection))->toBeTrue()
        ->and($user->aiConversations)->toHaveCount(1)
        ->and($project->aiConversations)->toHaveCount(1)
        ->and($conversation->messages)->toHaveCount(1)
        ->and($message->conversation->is($conversation))->toBeTrue();
});

it('deletes conversations and messages when a project is deleted, while deleting a connection keeps conversations', function () {
    $user = User::factory()->create();
    $project = Project::factory()->create(['owner_id' => $user->id]);
    $connection = AiConnection::factory()->default()->create(['user_id' => $user->id]);

    $conversation = AiConversation::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'ai_connection_id' => $connection->id,
    ]);

    $message = AiMessage::factory()->create([
        'ai_conversation_id' => $conversation->id,
    ]);

    // Deleting the AI connection nullifies ai_connection_id without deleting the conversation or messages.
    $connection->delete();

    expect($conversation->fresh())->not->toBeNull()
        ->and($conversation->fresh()->ai_connection_id)->toBeNull()
        ->and(AiMessage::find($message->id))->not->toBeNull();

    // Deleting the project cascades to conversations and their messages.
    $project->delete();

    expect(AiConversation::find($conversation->id))->toBeNull()
        ->and(AiMessage::find($message->id))->toBeNull();
});
