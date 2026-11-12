<?php

namespace App\Exceptions\Notebooks;

use App\Exceptions\BusinessRuleException;

final class NotebookPageCannotBeEmptyException extends BusinessRuleException
{
    public function __construct(string $message = 'Page cannot be empty. Delete explicitly if needed.')
    {
        parent::__construct($message);
    }
}
