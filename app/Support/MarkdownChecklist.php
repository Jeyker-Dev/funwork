<?php

namespace App\Support;

use App\Exceptions\ChecklistItemNotFound;
use Illuminate\Support\HtmlString;

class MarkdownChecklist
{
    private const string TASK_LINE_PATTERN = '/^(\s*(?:[-*+]|\d+[.)])\s+\[)([ xX])(\]\s+.*)$/';

    private const string FENCE_PATTERN = '/^\s*(```|~~~)/';

    /**
     * Toggle the N-th markdown checklist item (0-based) without altering any other lines.
     *
     * @throws ChecklistItemNotFound
     */
    public static function toggle(?string $markdown, int $targetIndex): string
    {
        if ($markdown === null || $targetIndex < 0) {
            throw ChecklistItemNotFound::atIndex($targetIndex);
        }

        $lineEnding = str_contains($markdown, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];

        $currentIndex = 0;
        $inFence = false;
        $fenceChar = '';
        $found = false;

        foreach ($lines as $lineNumber => $line) {
            if (preg_match(self::FENCE_PATTERN, $line, $fenceMatch) === 1) {
                $marker = $fenceMatch[1][0];
                if (! $inFence) {
                    $inFence = true;
                    $fenceChar = $marker;
                } elseif ($fenceChar === $marker) {
                    $inFence = false;
                    $fenceChar = '';
                }

                continue;
            }

            if ($inFence) {
                continue;
            }

            if (preg_match(self::TASK_LINE_PATTERN, $line, $matches) === 1) {
                if ($currentIndex === $targetIndex) {
                    $nextState = strtolower($matches[2]) === 'x' ? ' ' : 'x';
                    $lines[$lineNumber] = $matches[1].$nextState.$matches[3];
                    $found = true;
                    break;
                }

                $currentIndex++;
            }
        }

        if (! $found) {
            throw ChecklistItemNotFound::atIndex($targetIndex);
        }

        return implode($lineEnding, $lines);
    }

    /**
     * Render markdown HTML with interactive, accessible checklist checkboxes.
     */
    public static function toInteractiveHtml(?string $markdown): HtmlString
    {
        $html = (string) Markdown::toHtml($markdown);

        if ($html === '') {
            return new HtmlString('');
        }

        $index = 0;
        $updated = preg_replace_callback(
            '/<input\b([^>]*type="checkbox"[^>]*)>/i',
            function (array $matches) use (&$index): string {
                $isChecked = preg_match('/\bchecked\b/i', $matches[1]) === 1;
                $checkedAttr = $isChecked ? ' checked' : '';
                $currentIndex = $index++;

                return '<input type="checkbox"'.$checkedAttr
                    .' wire:click="toggleChecklistItem('.$currentIndex.')"'
                    .' aria-label="Checklist item '.($currentIndex + 1).'"'
                    .' class="task-checklist-checkbox">';
            },
            $html
        );

        return new HtmlString($updated ?? $html);
    }
}
