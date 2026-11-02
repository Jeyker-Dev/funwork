<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'Todo';
    case Doing = 'Doing';
    case Done = 'Done';
}
