<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'total_xp', 'current_streak', 'longest_streak', 'last_active_date', 'streak_freezes'])]
class UserStat extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'last_active_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
