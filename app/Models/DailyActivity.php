<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'date', 'xp', 'questions_answered', 'goal_met'])]
class DailyActivity extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'goal_met' => 'boolean',
        ];
    }
}
