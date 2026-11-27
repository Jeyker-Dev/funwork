<?php

namespace App\Models;

use App\Enums\AiProvider;
use Database\Factories\AiConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'provider',
    'label',
    'base_url',
    'model',
    'api_key',
    'allow_paid_models',
    'is_default',
])]
#[Hidden([
    'api_key',
])]
class AiConnection extends Model
{
    /** @use HasFactory<AiConnectionFactory> */
    use HasFactory;

    private const int MASKED_KEY_VISIBLE_CHARS = 4;

    private const string MASKED_KEY_PREFIX = '••••';

    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
            'allow_paid_models' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Returns the API key masked to reveal only its trailing four characters.
     */
    public function maskedApiKey(): string
    {
        return self::MASKED_KEY_PREFIX.mb_substr((string) $this->api_key, -self::MASKED_KEY_VISIBLE_CHARS);
    }

    /**
     * Resolves the base URL from the custom field or the preset provider config.
     */
    public function effectiveBaseUrl(): ?string
    {
        return $this->provider->isCustom()
            ? $this->base_url
            : $this->provider->defaultBaseUrl();
    }

    /**
     * Human-friendly label shown in lists and selectors.
     */
    public function displayName(): string
    {
        return filled($this->label)
            ? $this->label
            : $this->provider->label();
    }
}
