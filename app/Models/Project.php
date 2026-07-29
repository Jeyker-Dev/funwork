<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'icon',
        'type',
        'archived_at',
        'status',
        'due_at',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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

    public function getRecentActivities()
    {
        $notebooks = $this->notebooks()
            ->select('id', 'name', 'created_by', 'updated_at', \Illuminate\Support\Facades\DB::raw("'notebook' as type"));

        $files = $this->files()
            ->select('id', 'name', 'created_by', 'updated_at', \Illuminate\Support\Facades\DB::raw("'file' as type"));

        return $notebooks->unionAll($files)
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
