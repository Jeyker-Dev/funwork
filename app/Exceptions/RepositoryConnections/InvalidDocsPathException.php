<?php

namespace App\Exceptions\RepositoryConnections;

use App\Exceptions\BusinessRuleException;

final class InvalidDocsPathException extends BusinessRuleException
{
    public static function forValue(string $docsPath): self
    {
        return new self("Docs path [{$docsPath}] must be a valid relative path without '..' or a leading slash.");
    }
}
