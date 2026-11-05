<?php

namespace App\Models;

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

    public function notebook(): BelongsTo
    {
        return $this->belongsTo(Notebook::class);
    }
}
