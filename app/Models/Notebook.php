<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notebook extends Model
{
    protected $fillable = [
        'project_id',
        'title',
        'description',
        'created_by',
        'is_archived',
        'is_shared',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
