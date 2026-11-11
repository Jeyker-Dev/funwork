<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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

    private const array MARKDOWN_OPTIONS = [
        'html_input' => 'strip',
        'allow_unsafe_links' => false,
        'max_nesting_level' => 10,
    ];

    private const int EXCERPT_LENGTH = 200;

    public function changeStatus(TaskStatus $status): void
    {
        $this->update([
            'status' => $status,
        ]);
    }

    protected function descriptionHtml(): Attribute
    {
        return Attribute::make(
            get: function (): HtmlString {
                if (blank($this->description)) {
                    return new HtmlString('');
                }

                $html = Str::markdown($this->description, self::MARKDOWN_OPTIONS);

                return new HtmlString($html);
            }
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
