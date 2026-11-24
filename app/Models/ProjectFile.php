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

    private const int BYTES_PER_KILOBYTE = 1024;

    private const int BYTES_PER_MEGABYTE = 1024 * 1024;

    private const int SIZE_DECIMALS = 2;

    private const array MIME_TYPE_LABELS = [
        'application/pdf' => 'PDF',
        'application/msword' => 'Word document',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word document',
        'application/vnd.ms-excel' => 'Spreadsheet',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Spreadsheet',
        'application/vnd.ms-powerpoint' => 'Presentation',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'Presentation',
        'text/csv' => 'CSV',
        'text/plain' => 'Text',
        'text/markdown' => 'Markdown',
        'application/json' => 'JSON',
        'application/zip' => 'ZIP archive',
        'application/x-zip-compressed' => 'ZIP archive',
    ];

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
    protected function typeLabel(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->category === FileCategory::Image) {
                return 'Image';
            }

            $mime = strtolower((string) $this->mime_type);

            if (array_key_exists($mime, self::MIME_TYPE_LABELS)) {
                return self::MIME_TYPE_LABELS[$mime];
            }

            return match ($this->category) {
                FileCategory::Pdf => 'PDF',
                FileCategory::Document => 'Document',
                FileCategory::Image => 'Image',
            };
        });
    }

    /**
     * @return Attribute<string, never>
     */
    protected function formattedSize(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->size >= self::BYTES_PER_MEGABYTE) {
                return number_format($this->size / self::BYTES_PER_MEGABYTE, self::SIZE_DECIMALS).' MB';
            }

            if ($this->size >= self::BYTES_PER_KILOBYTE) {
                return (int) round($this->size / self::BYTES_PER_KILOBYTE).' KB';
            }

            return $this->size.' B';
        });
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
