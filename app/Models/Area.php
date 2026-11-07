<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory;

    public const ALLOWED_COLORS = [
        'blue',
        'green',
        'amber',
        'purple',
        'red',
        'cyan',
        'pink',
        'zinc',
    ];

    protected $fillable = [
        'project_id',
        'name',
        'slug',
        'color',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function notebooks(): HasMany
    {
        return $this->hasMany(Notebook::class);
    }
}
