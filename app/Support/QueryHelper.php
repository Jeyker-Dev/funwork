<?php

namespace App\Support;

class QueryHelper
{
    /**
     * Escape special LIKE characters (!, %, _) using '!' as the escape character.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value
        );
    }
}
