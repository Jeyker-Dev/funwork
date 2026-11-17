<?php

namespace App\Exceptions\Notebooks;

use App\Exceptions\BusinessRuleException;

final class NotebookPageCannotBeEmptyException extends BusinessRuleException
{
    protected $message = 'Page cannot be empty. Delete explicitly if needed.';
}
