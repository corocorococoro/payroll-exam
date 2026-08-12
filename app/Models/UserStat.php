<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $user_id
 * @property int $total_xp
 * @property string $mascot_style
 * @property int $current_streak
 * @property int $longest_streak
 * @property Carbon|null $last_active_date
 * @property int $streak_freezes
 */
#[Fillable(['user_id', 'total_xp', 'mascot_style', 'current_streak', 'longest_streak', 'last_active_date', 'streak_freezes'])]
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
