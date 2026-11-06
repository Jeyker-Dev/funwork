<?php

namespace App\Models;

use App\Jobs\SyncNotebookToGoogleDocJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotebookPage extends Model
{
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
}
