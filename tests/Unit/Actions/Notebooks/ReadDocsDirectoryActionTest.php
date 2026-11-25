<?php

use App\Actions\Notebooks\ReadDocsDirectoryAction;

function deleteDirectoryRecursively(string $directory): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($directory);
}

beforeEach(function (): void {
    $this->tempDir = sys_get_temp_dir().'/funwork_read_docs_'.uniqid('', true);
    mkdir($this->tempDir, 0777, true);
});

afterEach(function (): void {
    if (isset($this->tempDir) && is_dir($this->tempDir)) {
        deleteDirectoryRecursively($this->tempDir);
    }
});

it('returns docs/ keys in natural case-insensitive order while excluding archive and non-markdown files', function (): void {
    file_put_contents($this->tempDir.'/README.md', '# Project Docs');
    file_put_contents($this->tempDir.'/10-ten.md', '# Ten');
    file_put_contents($this->tempDir.'/2-two.md', '# Two');
    file_put_contents($this->tempDir.'/01-Alpha.md', '# Alpha');
    file_put_contents($this->tempDir.'/notes.txt', 'Ignored text file');
    file_put_contents($this->tempDir.'/meta.json', '{"ignored": true}');

    mkdir($this->tempDir.'/areas', 0777, true);
    file_put_contents($this->tempDir.'/areas/backend.md', '# Backend');

    mkdir($this->tempDir.'/sub', 0777, true);
    file_put_contents($this->tempDir.'/sub/Guide.md', '# Guide');

    mkdir($this->tempDir.'/archive/nested', 0777, true);
    file_put_contents($this->tempDir.'/archive/old.md', '# Old');
    file_put_contents($this->tempDir.'/archive/nested/deep.md', '# Deep');

    $files = (new ReadDocsDirectoryAction)($this->tempDir);

    expect(array_keys($files))->toBe([
        'docs/01-Alpha.md',
        'docs/2-two.md',
        'docs/10-ten.md',
        'docs/areas/backend.md',
        'docs/README.md',
        'docs/sub/Guide.md',
    ])->and($files['docs/01-Alpha.md'])->toBe('# Alpha')
        ->and($files['docs/README.md'])->toBe('# Project Docs')
        ->and($files['docs/areas/backend.md'])->toBe('# Backend')
        ->and($files['docs/sub/Guide.md'])->toBe('# Guide');
});

it('returns an empty array when no eligible markdown files exist', function (): void {
    file_put_contents($this->tempDir.'/notes.txt', 'Ignored');
    mkdir($this->tempDir.'/archive', 0777, true);
    file_put_contents($this->tempDir.'/archive/old.md', '# Old');

    $files = (new ReadDocsDirectoryAction)($this->tempDir);

    expect($files)->toBe([]);
});

it('throws RuntimeException when a markdown file is unreadable', function (): void {
    $unreadableFile = $this->tempDir.'/secret.md';
    file_put_contents($unreadableFile, '# Secret');
    chmod($unreadableFile, 0000);

    try {
        expect(fn (): array => (new ReadDocsDirectoryAction)($this->tempDir))
            ->toThrow(RuntimeException::class);
    } finally {
        chmod($unreadableFile, 0644);
    }
});
