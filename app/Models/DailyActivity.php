<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property int $xp
 * @property int $questions_answered
 * @property bool $goal_met
 */
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
