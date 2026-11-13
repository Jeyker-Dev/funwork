<?php

namespace App\Models;

use App\Enums\DecisionStatus;
use App\Support\Markdown;
use Database\Factories\DecisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;

#[Fillable([
    'project_id',
    'number',
    'title',
    'status',
    'context',
    'decision',
    'consequences',
    'decided_at',
    'superseded_by_id',
    'created_by',
])]
class Decision extends Model
{
    /** @use HasFactory<DecisionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => DecisionStatus::class,
            'decided_at' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn (): string => sprintf('ADR-%03d', $this->number),
        );
    }

    protected function contextHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): HtmlString => Markdown::toHtml($this->context),
        )->shouldCache();
    }

    protected function decisionHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): HtmlString => Markdown::toHtml($this->decision),
        )->shouldCache();
    }

    protected function consequencesHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): HtmlString => Markdown::toHtml($this->consequences),
        )->shouldCache();
    }
}
