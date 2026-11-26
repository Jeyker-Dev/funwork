<?php

namespace App\Models;

use App\Enums\RepositoryConnectionStatus;
use Database\Factories\RepositoryConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'area_id',
    'repository',
    'branch',
    'docs_path',
    'token',
    'webhook_secret',
    'last_synced_sha',
    'last_synced_at',
    'status',
    'last_error',
])]
#[Hidden([
    'token',
    'webhook_secret',
])]
class RepositoryConnection extends Model
{
    /** @use HasFactory<RepositoryConnectionFactory> */
    use HasFactory;

    public const int WEBHOOK_SECRET_LENGTH = 64;

    private const int MASKED_TOKEN_VISIBLE_CHARS = 4;

    private const string MASKED_TOKEN_PREFIX = '••••';

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'last_synced_at' => 'datetime',
            'status' => RepositoryConnectionStatus::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Returns the token masked to reveal only its trailing four characters.
     */
    public function maskedToken(): string
    {
        return self::MASKED_TOKEN_PREFIX.mb_substr((string) $this->token, -self::MASKED_TOKEN_VISIBLE_CHARS);
    }

    /**
     * Whether the connection is active and eligible for automatic webhook syncs.
     */
    public function isActive(): bool
    {
        return $this->status === RepositoryConnectionStatus::Active;
    }

    /**
     * Whether the connection is broken and paused until updated or manually resynced.
     */
    public function isBroken(): bool
    {
        return $this->status === RepositoryConnectionStatus::Broken;
    }

    /**
     * Records a successful docs sync at the given commit SHA, restores Active status, and clears any prior error.
     */
    public function markSynced(string $commitSha): void
    {
        $this->update([
            'last_synced_sha' => $commitSha,
            'last_synced_at' => now(),
            'status' => RepositoryConnectionStatus::Active,
            'last_error' => null,
        ]);
    }

    /**
     * Marks the connection as broken with a safe error summary.
     */
    public function markBroken(string $error): void
    {
        $this->update([
            'status' => RepositoryConnectionStatus::Broken,
            'last_error' => $error,
        ]);
    }

    /**
     * Records a safe error summary without altering the connection status.
     */
    public function recordError(string $error): void
    {
        $this->update([
            'last_error' => $error,
        ]);
    }

    /**
     * Generates a cryptographically secure random webhook secret.
     */
    public static function generateWebhookSecret(): string
    {
        return bin2hex(random_bytes(intdiv(self::WEBHOOK_SECRET_LENGTH, 2)));
    }
}
