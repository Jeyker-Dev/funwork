<?php

namespace App\Models;

use App\Enums\TaskStatus;
use App\Exceptions\AreaNotFound;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

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

    private const int FULL_PERCENT = 100;

    /**
     * @var list<string>
     */
    public const array SUPPORTED_ICONS = [
        'wrench',
        'pencil-square',
        'eye',
        'folder',
        'document',
        'home',
        'cog',
        'user',
        'code-bracket',
        'book-open',
        'sparkles',
        'cube',
    ];

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    public function unarchive(): void
    {
        $this->update(['archived_at' => null]);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class)->orderBy('position');
    }

    public function findAreaByKey(string $key): ?Area
    {
        $normalized = strtolower(Str::slug($key));

        return $this->areas()->whereRaw('LOWER(slug) = ?', [$normalized])->first();
    }

    /**
     * @throws AreaNotFound
     */
    public function areaByKey(string $key): Area
    {
        return $this->findAreaByKey($key) ?? throw AreaNotFound::for($key, $this);
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

    /**
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeWithTaskCounts(Builder $query): Builder
    {
        return $query->withCount([
            'tasks',
            'tasks as done_tasks_count' => fn (Builder $taskQuery): Builder => $taskQuery->where('status', TaskStatus::Done->value),
        ]);
    }

    public function totalTasksCount(): int
    {
        $this->assertTaskCountsLoaded();

        return (int) $this->attributes['tasks_count'];
    }

    public function doneTasksCount(): int
    {
        $this->assertTaskCountsLoaded();

        return (int) $this->attributes['done_tasks_count'];
    }

    public function taskProgressPercent(): int
    {
        $totalTasks = $this->totalTasksCount();

        if ($totalTasks === 0) {
            return 0;
        }

        return (int) round(($this->doneTasksCount() / $totalTasks) * self::FULL_PERCENT);
    }

    public function lastActivityAt(): CarbonInterface
    {
        if (! $this->updated_at instanceof CarbonInterface) {
            throw new LogicException('Project updated_at timestamp must be present to determine last activity.');
        }

        return collect([
            $this->updated_at,
            $this->tasks_max_updated_at ? CarbonImmutable::parse($this->tasks_max_updated_at) : null,
            $this->notebooks_max_updated_at ? CarbonImmutable::parse($this->notebooks_max_updated_at) : null,
            $this->files_max_updated_at ? CarbonImmutable::parse($this->files_max_updated_at) : null,
        ])
            ->filter()
            ->sortByDesc(fn (CarbonInterface $timestamp): int => $timestamp->getTimestamp())
            ->first();
    }

    public function compareRecentActivityTo(self $other): int
    {
        $timeComparison = $this->lastActivityAt()->getTimestamp() <=> $other->lastActivityAt()->getTimestamp();

        if ($timeComparison !== 0) {
            return $timeComparison;
        }

        return $this->id <=> $other->id;
    }

    private function assertTaskCountsLoaded(): void
    {
        if (! array_key_exists('tasks_count', $this->attributes) || ! array_key_exists('done_tasks_count', $this->attributes)) {
            throw new LogicException('Task counts must be eager-loaded via Project::withTaskCounts().');
        }
    }
}
