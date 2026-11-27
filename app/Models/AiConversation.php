<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use Database\Factories\AiConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'project_id',
    'ai_connection_id',
    'title',
])]
class AiConversation extends Model
{
    /** @use HasFactory<AiConversationFactory> */
    use HasFactory;

    public const int TITLE_MAX_LENGTH = 80;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function aiConnection(): BelongsTo
    {
        return $this->belongsTo(AiConnection::class, 'ai_connection_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'ai_conversation_id')->orderBy('id');
    }

    public function hasPendingReply(): bool
    {
        return $this->messages()
            ->where('role', AiMessageRole::Assistant->value)
            ->where('status', AiMessageStatus::Pending->value)
            ->exists();
    }

    public function latestAssistantMessage(): ?AiMessage
    {
        /** @var AiMessage|null $message */
        $message = $this->messages()
            ->where('role', AiMessageRole::Assistant->value)
            ->reorder('id', 'desc')
            ->first();

        return $message;
    }

    /**
     * Resolves the conversation's AI connection, falling back to the user's current default.
     */
    public function preferredAiConnection(): ?AiConnection
    {
        if ($this->aiConnection instanceof AiConnection) {
            return $this->aiConnection;
        }

        /** @var AiConnection|null $default */
        $default = $this->user?->aiConnections()
            ->where('is_default', true)
            ->first()
            ?? $this->user?->aiConnections()->latest('id')->first();

        return $default;
    }

    public function generateTitleFromQuestion(string $question): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($question));
        $normalized = is_string($collapsed) ? $collapsed : trim($question);

        return Str::limit($normalized, self::TITLE_MAX_LENGTH, '…');
    }
}
