<?php

namespace App\Enums;

enum Difficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    public function xp(): int
    {
        return match ($this) {
            self::Easy => 10,
            self::Medium => 15,
            self::Hard => 20,
        };
    }
}
