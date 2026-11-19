<?php

namespace App\Enums;

enum FileCategory: string
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Document = 'document';

    private const string IMAGE_MIME_PREFIX = 'image/';

    private const string PDF_MIME_TYPE = 'application/pdf';

    public static function fromMimeType(string $mimeType): self
    {
        return match (true) {
            str_starts_with($mimeType, self::IMAGE_MIME_PREFIX) => self::Image,
            $mimeType === self::PDF_MIME_TYPE => self::Pdf,
            default => self::Document,
        };
    }

    public function iconColor(): string
    {
        return match ($this) {
            self::Image => 'text-brand',
            self::Pdf => 'text-red-500',
            self::Document => 'text-blue-500',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Image => 'photo',
            self::Pdf => 'document-text',
            self::Document => 'document',
        };
    }

    public function previewClasses(): string
    {
        return match ($this) {
            self::Image => 'text-brand',
            self::Pdf => 'text-red-500 bg-red-50 dark:bg-red-950/30 p-3 rounded-xl shadow-sm',
            self::Document => 'text-blue-500 bg-blue-50 dark:bg-blue-950/30 p-3 rounded-xl shadow-sm',
        };
    }
}
