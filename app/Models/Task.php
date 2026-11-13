<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Support\Markdown;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'project_id',
        'position',
        'status',
        'due_date',
        'priority',
        'area_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public const int MAX_DESCRIPTION_LENGTH = 65535;

    private const int EXCERPT_LENGTH = 200;

    public function changeStatus(TaskStatus $status): void
    {
        $this->update([
            'status' => $status,
        ]);
    }

    public function updateDescription(string $description): void
    {
        $this->update([
            'description' => $description,
        ]);
    }

    protected function descriptionHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): HtmlString => Markdown::toHtml($this->description)
        )->shouldCache();
    }

    protected function descriptionExcerpt(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (blank($this->description)) {
                    return '';
                }

                $html = (string) $this->description_html;
                $decoded = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return Str::limit(Str::squish($decoded), self::EXCERPT_LENGTH);
            }
        )->shouldCache();
    }
}
