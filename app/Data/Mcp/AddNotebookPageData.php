<?php

namespace App\Data\Mcp;

use Laravel\Mcp\Request;

readonly class AddNotebookPageData
{
    public const int MAX_BODY_LENGTH = 65535;

    public function __construct(
        public int $notebookId,
        public string $title,
        public string $body,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'notebook_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:'.self::MAX_BODY_LENGTH],
        ]);

        return new self(
            notebookId: (int) $validated['notebook_id'],
            title: trim($validated['title']),
            body: $validated['body'],
        );
    }
}
