<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'question_id', 'box', 'due_date', 'lapses'])]
class ReviewItem extends Model
{
    /** Leitner 方式の復習間隔（box => 日数） */
    public const array INTERVALS = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30];

    public const int MAX_BOX = 5;

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
