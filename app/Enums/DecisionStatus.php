<?php

namespace App\Enums;

enum DecisionStatus: string
{
    case Proposed = 'Proposed';
    case Accepted = 'Accepted';
    case Rejected = 'Rejected';
    case Superseded = 'Superseded';

    public function fluxColor(): string
    {
        return match ($this) {
            self::Proposed => 'amber',
            self::Accepted => 'green',
            self::Rejected => 'red',
            self::Superseded => 'zinc',
        };
    }

    public function label(): string
    {
        return $this->value;
    }
}
