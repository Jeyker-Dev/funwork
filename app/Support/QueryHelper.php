<?php

namespace App\Support;

use Illuminate\Support\Str;

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

    /**
     * Apply area filter to a task query builder.
     */
    public static function applyAreaFilter($query, ?string $area): void
    {
        if (empty($area) || $area === 'all') {
            return;
        }

        if (in_array(strtolower($area), ['none', 'no-area', 'no_area', 'no area', '0'], true)) {
            $query->whereNull('area_id');

            return;
        }

        if (is_numeric($area)) {
            $query->where('area_id', (int) $area);

            return;
        }

        $query->whereHas('area', function ($q) use ($area) {
            $q->whereRaw('LOWER(slug) = ?', [strtolower(Str::slug($area))])
                ->orWhereRaw('LOWER(name) = ?', [strtolower($area)]);
        });
    }
}
