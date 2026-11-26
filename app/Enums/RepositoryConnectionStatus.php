<?php

namespace App\Enums;

enum RepositoryConnectionStatus: string
{
    case Active = 'active';
    case Broken = 'broken';
}
