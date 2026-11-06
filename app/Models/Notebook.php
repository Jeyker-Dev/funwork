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

    protected function casts(): array
    {
        return [
            'google_doc_synced_at' => 'datetime',
            'content_changed_at' => 'datetime',
        ];
    }

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

    public function hasUnsyncedChanges(): bool
    {
        if (! $this->google_doc_id) {
            return false;
        }

        if ($this->google_doc_synced_at === null) {
            return true;
        }

        return $this->content_changed_at !== null && $this->content_changed_at->gt($this->google_doc_synced_at);
    }
}
