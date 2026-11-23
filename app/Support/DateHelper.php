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
     * Determine whether a timestamp is less than a minute from now.
     */
    public static function isJustNow(CarbonInterface $timestamp): bool
    {
        return $timestamp->diffInSeconds(now(), absolute: true) < self::JUST_NOW_THRESHOLD_SECONDS;
    }

    /**
     * Format a compact relative age, returning "now" under one minute.
     */
    public static function shortRelativeAge(CarbonInterface $timestamp): string
    {
        if (self::isJustNow($timestamp)) {
            return 'now';
        }

        return $timestamp->shortAbsoluteDiffForHumans();
    }

    /**
     * Format a natural English relative age, returning "just now" under one minute.
     */
    public static function relativeAge(CarbonInterface $timestamp): string
    {
        if (self::isJustNow($timestamp)) {
            return 'just now';
        }

        return $timestamp->locale('en')->diffForHumans();
    }
}
