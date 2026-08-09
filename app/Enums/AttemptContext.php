<?php

namespace App\Enums;

enum AttemptContext: string
{
    case Lesson = 'lesson';
    case Review = 'review';
    case Mock = 'mock';
}
