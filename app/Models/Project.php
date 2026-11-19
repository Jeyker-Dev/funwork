<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'owner_id',
    'name',
    'description',
    'icon',
    'type',
    'archived_at',
    'status',
    'due_at',
])]
class Project extends Model
{
    use HasFactory;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class)->orderBy('position');
    }

    public function areaByKey(string $key): ?Area
    {
        $normalized = strtolower(Str::slug($key));

        return $this->areas()->whereRaw('LOWER(slug) = ?', [$normalized])->first();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }

    public function notebooks(): HasMany
    {
        return $this->hasMany(Notebook::class, 'project_id');
    }

    public function folders(): HasMany
    {
        return $this->hasMany(Folder::class, 'project_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class, 'project_id');
    }

    public function inboxItems(): HasMany
    {
        return $this->hasMany(InboxItem::class, 'project_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(Decision::class, 'project_id');
    }
}
