<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'date', 'quest_type', 'target', 'progress', 'completed', 'xp_reward'])]
class DailyQuest extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'completed' => 'boolean',
        ];
    }
}
