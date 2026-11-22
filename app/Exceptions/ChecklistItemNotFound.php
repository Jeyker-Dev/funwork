<?php

namespace App\Exceptions;

final class ChecklistItemNotFound extends BusinessRuleException
{
    public static function atIndex(int $index): self
    {
        return new self("Checklist item at index {$index} could not be found.");
    }
}
