<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $lesson_id
 * @property int $crown_level
 * @property int $completed_count
 * @property Carbon|null $last_completed_at
 */
#[Fillable(['user_id', 'lesson_id', 'crown_level', 'completed_count', 'last_completed_at'])]
class LessonProgress extends Model
{
    public const int MAX_CROWN = 5;

    protected function casts(): array
    {
        return [
            'last_completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Lesson, $this> */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
