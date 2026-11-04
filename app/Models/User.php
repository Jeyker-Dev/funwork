<?php

namespace App\Models;

use App\Support\DateHelper;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'username', 'email', 'password', 'timezone', 'profile_photo_path'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

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
        ];
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

            // If the disk is s3, generate a temporary signed URL valid for 1 hour
            if ($disk === 's3') {
                return Storage::disk($disk)->temporaryUrl(
                    $this->profile_photo_path,
                    now()->addMinutes(60)
                );
            }

            // Otherwise, for local disk, use standard Storage::url()
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
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    /**
     * Get the current date in the user's timezone.
     */
    public function localDate(): string
    {
        return DateHelper::userToday($this);
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
}
