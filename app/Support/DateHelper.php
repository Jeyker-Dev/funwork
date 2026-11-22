<?php

namespace App\Support;

use App\Models\User;

class DateHelper
{
    public static function userTimezone(?User $user = null): string
    {
        return ($user ?? auth()->user())?->timezone ?? config('app.timezone', 'UTC');
    }

    /**
     * Get the current date string (Y-m-d) in the user's timezone.
     */
    public static function userToday(?User $user = null): string
    {
        return now(self::userTimezone($user))->toDateString();
    }
}
