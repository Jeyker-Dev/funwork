<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use App\Enums\AiMessageStatus;
use Database\Factories\AiMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ai_conversation_id',
    'role',
    'content',
    'model',
    'status',
    'error',
])]
class AiMessage extends Model
{
    /** @use HasFactory<AiMessageFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'status' => AiMessageStatus::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function markCompleted(string $content, string $model): void
    {
        $this->update([
            'content' => $content,
            'model' => $model,
            'status' => AiMessageStatus::Completed,
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => AiMessageStatus::Failed,
            'error' => $error,
        ]);
    }

    public function resetToPending(): void
    {
        $this->update([
            'content' => '',
            'model' => null,
            'status' => AiMessageStatus::Pending,
            'error' => null,
        ]);
    }
}
