<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Support\DateHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * @return array{tasks_count: int, today_tasks_count: int, inbox_count: int}
     */
    public function dashboardStats(?User $user = null): array
    {
        $localToday = DateHelper::userToday($user);

        return [
            'tasks_count' => $this->tasks()
                ->where('status', '!=', TaskStatus::Done->value)
                ->count(),
            'today_tasks_count' => $this->tasks()
                ->where('status', '!=', TaskStatus::Done->value)
                ->whereDate('due_date', $localToday)
                ->count(),
            'inbox_count' => $this->inboxItems()->count(),
        ];
    }

    public function getRecentActivities()
    {
        $notebooks = $this->notebooks()
            ->select('id', 'name', 'created_by', 'updated_at', DB::raw("'notebook' as type"));

        $files = $this->files()
            ->select('id', 'name', 'created_by', 'updated_at', DB::raw("'file' as type"));

        return $notebooks->unionAll($files)
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
