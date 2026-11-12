<?php

namespace App\Exceptions\Notebooks;

use DomainException;

final class NotebookPageCannotBeEmptyException extends DomainException
{
    public function __construct(string $message = 'Page cannot be empty. Delete explicitly if needed.')
    {
        parent::__construct($message);
    }
}
