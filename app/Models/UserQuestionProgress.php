<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $user_id
 * @property int $question_id
 * @property string $state
 * @property int $box
 * @property CarbonImmutable|null $due_at
 * @property int $lapses
 * @property int $correct_count
 * @property int $incorrect_count
 * @property int $content_revision_seen
 * @property CarbonImmutable|null $first_seen_at
 * @property CarbonImmutable|null $last_seen_at
 */
#[Fillable([
    'user_id', 'question_id', 'state', 'box', 'due_at', 'lapses', 'correct_count',
    'incorrect_count', 'content_revision_seen', 'first_seen_at', 'last_seen_at',
])]
class UserQuestionProgress extends Model
{
    protected $table = 'user_question_progress';

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
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
