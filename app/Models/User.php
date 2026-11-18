<?php

namespace App\Models;

use App\Support\DateHelper;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable([
    'name',
    'username',
    'email',
    'password',
    'timezone',
    'profile_photo_path',
    'digest_enabled',
    'digest_hour',
    'last_digest_sent_at',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable, TwoFactorAuthenticatable;

    public const int DEFAULT_DIGEST_HOUR = 8;

    private const string S3_DISK = 's3';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'digest_enabled' => 'boolean',
            'digest_hour' => 'integer',
            'last_digest_sent_at' => 'datetime',
        ];
    }

    public function updateDigestSettings(bool $digestEnabled, int $digestHour): void
    {
        $this->update([
            'digest_enabled' => $digestEnabled,
            'digest_hour' => $digestHour,
        ]);
    }

    public function markDailyDigestSent(): void
    {
        $this->update([
            'last_digest_sent_at' => now(),
        ]);
    }

    public function isDailyDigestDue(): bool
    {
        if (! $this->digest_enabled) {
            return false;
        }

        $timezone = $this->timezone ?? config('app.timezone', 'UTC');
        $userLocalNow = now($timezone);

        if ((int) $userLocalNow->format('H') !== $this->digest_hour) {
            return false;
        }

        if ($this->last_digest_sent_at === null) {
            return true;
        }

        $lastSentLocalDate = CarbonImmutable::instance($this->last_digest_sent_at)
            ->setTimezone($timezone)
            ->toDateString();

        return $lastSentLocalDate !== $userLocalNow->toDateString();
    }

    /**
     * Get the display name for the user
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->username ?? explode(' ', $this->name)[0];
    }

    /**
     * Get the avatar url for the user
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->profile_photo_path) {
            $disk = config('filesystems.default');

            if ($disk === self::S3_DISK) {
                return Storage::disk($disk)->temporaryUrl(
                    $this->profile_photo_path,
                    now()->addMinutes(60)
                );
            }

            return Storage::disk($disk)->url($this->profile_photo_path);
        }

        return 'https://unavatar.io/'.urlencode($this->email).'?fallback='.urlencode('https://ui-avatars.com/api/?name='.$this->name.'&color=7F9CF5&background=EBF4FF');
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn (string $word): string => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the current date in the user's timezone.
     */
    public function localDate(): string
    {
        return DateHelper::userToday($this);
    }

    public function googleAccount(): HasOne
    {
        return $this->hasOne(GoogleAccount::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class);
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class, 'created_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'created_by');
    }

    public function inboxItems(): HasMany
    {
        return $this->hasMany(InboxItem::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }
}
