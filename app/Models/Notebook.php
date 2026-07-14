<?php

namespace App\Models;

use Database\Factories\NotebookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Notebook extends Model
{
    /** @use HasFactory<NotebookFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'name',
        'description',
        'created_by',
        'is_archived',
        'is_shared',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(NotebookPage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes for filtering
    public function scopeShared($query)
    {
        return $query->where('is_shared', true);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }
}
