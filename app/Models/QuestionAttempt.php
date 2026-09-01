<?php

namespace App\Models;

use App\Enums\AttemptContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $content_revision
 * @property bool $is_correct
 */
#[Fillable(['user_id', 'question_id', 'content_revision', 'lesson_id', 'context', 'is_correct', 'given_answer', 'xp_earned'])]
class QuestionAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'context' => AttemptContext::class,
            'content_revision' => 'integer',
            'is_correct' => 'boolean',
            'given_answer' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
