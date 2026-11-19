<?php

namespace App\Models;

use App\Enums\FileCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id',
    'folder_id',
    'created_by',
    'name',
    'file_path',
    'disk',
    'mime_type',
    'size',
])]
class ProjectFile extends Model
{
    use HasFactory, SoftDeletes;

    private const string FALLBACK_EXTENSION_LABEL = 'File';

    private const string MIME_TYPE_SEPARATOR = '/';

    private const string FOLDER_PATH_SEPARATOR = ' / ';

    private const int BYTES_PER_MEGABYTE = 1024 * 1024;

    private const int SIZE_DECIMALS = 2;

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return Attribute<FileCategory, never>
     */
    protected function category(): Attribute
    {
        return Attribute::get(fn (): FileCategory => FileCategory::fromMimeType($this->mime_type));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function extensionLabel(): Attribute
    {
        return Attribute::get(fn (): string => str_contains($this->mime_type, self::MIME_TYPE_SEPARATOR)
            ? str($this->mime_type)->after(self::MIME_TYPE_SEPARATOR)->toString()
            : self::FALLBACK_EXTENSION_LABEL);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function sizeInMegabytes(): Attribute
    {
        return Attribute::get(fn (): string => number_format($this->size / self::BYTES_PER_MEGABYTE, self::SIZE_DECIMALS));
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function folderPath(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->folder
            ? collect($this->folder->breadcrumbs)->pluck('name')->join(self::FOLDER_PATH_SEPARATOR)
            : null);
    }
}
