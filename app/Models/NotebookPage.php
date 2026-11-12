<?php

namespace App\Models;

use App\Jobs\SyncNotebookToGoogleDocJob;
use Database\Factories\NotebookPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotebookPage extends Model
{
    /** @use HasFactory<NotebookPageFactory> */
    use HasFactory;

    protected $fillable = [
        'notebook_id',
        'source_path',
        'content',
        'order',
        'created_by',
    ];

    protected $casts = [
        'content' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(function (NotebookPage $page) {
            $page->recordNotebookContentChanged();
        });

        static::deleted(function (NotebookPage $page) {
            $page->recordNotebookContentChanged();
        });
    }

    protected function recordNotebookContentChanged(): void
    {
        $notebook = $this->notebook;

        if (! $notebook) {
            return;
        }

        $notebook->forceFill([
            'content_changed_at' => now(),
        ])->save();

        if ($notebook->google_doc_id) {
            SyncNotebookToGoogleDocJob::dispatch($notebook)->delay(now()->addMinutes(2));
        }
    }

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }

    public function previousPage(): ?self
    {
        return $this->notebook->pages()
            ->where('order', '<', $this->order)
            ->orderBy('order', 'desc')
            ->first();
    }

    public function nextPage(): ?self
    {
        return $this->notebook->pages()
            ->where('order', '>', $this->order)
            ->orderBy('order', 'asc')
            ->first();
    }

    public function fallbackPageAfterDelete(): ?self
    {
        return $this->previousPage() ?? $this->nextPage();
    }
}
