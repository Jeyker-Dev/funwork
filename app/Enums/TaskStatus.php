<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'Todo';
    case Doing = 'Doing';
    case Done = 'Done';

    public function dot(): string
    {
        return match ($this) {
            self::Todo => 'bg-orange-500',
            self::Doing => 'bg-yellow-500',
            self::Done => 'bg-green-500',
        };
    }

    public function columnBorder(): string
    {
        return match ($this) {
            self::Todo => 'border-[rgba(255,128,64,0.48)]',
            self::Doing => 'border-[rgba(255,220,70,0.38)]',
            self::Done => 'border-[rgba(38,186,112,0.33)]',
        };
    }

    public function columnBg(): string
    {
        return match ($this) {
            self::Todo => 'bg-[rgba(255,128,64,0.16)]',
            self::Doing => 'bg-[rgba(255,220,70,0.17)]',
            self::Done => 'bg-[rgba(38,186,112,0.13)]',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Todo => 'border-orange-200 bg-orange-50 hover:bg-orange-200 text-orange-500',
            self::Doing => 'border-yellow-200 bg-yellow-50 hover:bg-yellow-200 text-yellow-600',
            self::Done => 'border-green-200 bg-green-50 hover:bg-green-200 text-green-600',
        };
    }

    public function cardClasses(): string
    {
        return match ($this) {
            self::Todo => 'border-[rgba(255,128,64,0.48)] bg-[rgba(255,128,64,0.23)] hover:bg-[rgba(255,128,64,0.33)]',
            self::Doing => 'border-[rgba(255,220,70,0.38)] bg-[rgba(255,220,70,0.17)] hover:bg-[rgba(255,220,70,0.27)]',
            self::Done => 'border-[rgba(38,186,112,0.33)] bg-[rgba(38,186,112,0.13)] hover:bg-[rgba(38,186,112,0.23)]',
        };
    }

    public function text(): string
    {
        return match ($this) {
            self::Todo => 'text-orange-500',
            self::Doing => 'text-yellow-500',
            self::Done => 'text-green-500',
        };
    }

    public function accent(): string
    {
        return match ($this) {
            self::Todo => 'bg-orange-400',
            self::Doing => 'bg-yellow-400',
            self::Done => 'bg-green-400',
        };
    }

    public function fluxColor(): string
    {
        return match ($this) {
            self::Todo => 'orange',
            self::Doing => 'yellow',
            self::Done => 'green',
        };
    }
}
