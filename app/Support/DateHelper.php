<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;

class DateHelper
{
    private const int JUST_NOW_THRESHOLD_SECONDS = 60;

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

    /**
     * Format a compact relative age, returning "now" under one minute.
     */
    public static function shortRelativeAge(CarbonInterface $timestamp): string
    {
        if ($timestamp->diffInSeconds(now(), absolute: true) < self::JUST_NOW_THRESHOLD_SECONDS) {
            return 'now';
        }

        return $timestamp->shortAbsoluteDiffForHumans();
    }
}
