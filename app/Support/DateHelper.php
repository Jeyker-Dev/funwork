<?php

namespace App\Support;

use App\Models\User;

class DateHelper
{
    /**
     * Get the current date string (Y-m-d) in the user's timezone.
     */
    public static function userToday(?User $user = null): string
    {
        $timezone = ($user ?? auth()->user())?->timezone ?? config('app.timezone', 'UTC');

        return now($timezone)->toDateString();
    }
}
