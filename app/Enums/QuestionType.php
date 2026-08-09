<?php

namespace App\Enums;

enum QuestionType: string
{
    case Choice = 'choice';
    case Numeric = 'numeric';
}
