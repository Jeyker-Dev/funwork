<?php

namespace App\Actions\Notebooks;

use RuntimeException;
use Symfony\Component\Finder\Finder;

/**
 * Reads markdown documentation files from a directory on disk.
 */
class ReadDocsDirectoryAction
{
    private const string EXCLUDED_DIRECTORY = 'archive';

    private const string SOURCE_PATH_PREFIX = 'docs/';

    /**
     * @return array<string, string>
     */
    public function __invoke(string $docsPath): array
    {
        $finder = (new Finder)->files()->in($docsPath)->name('*.md')->exclude(self::EXCLUDED_DIRECTORY);
        $files = [];

        foreach ($finder as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $sourcePath = self::SOURCE_PATH_PREFIX.ltrim($relativePath, '/');
            $realPath = $file->getRealPath();
            $body = $realPath !== false && is_readable($realPath) ? file_get_contents($realPath) : false;

            if ($body === false) {
                throw new RuntimeException("Could not read documentation file [{$file->getPathname()}].");
            }

            $files[$sourcePath] = $body;
        }

        return $files;
    }
}
