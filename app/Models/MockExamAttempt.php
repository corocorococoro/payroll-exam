<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $mock_exam_id
 * @property int $time_limit_minutes
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property array<string, string>|null $answers
 * @property int|null $score
 * @property array<string, mixed>|null $section_scores
 */
#[Fillable([
    'user_id', 'mock_exam_id', 'time_limit_minutes', 'started_at', 'finished_at',
    'answers', 'score', 'section_scores',
])]
class MockExamAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'answers' => 'array',
            'section_scores' => 'array',
        ];
    }

    /** @return BelongsTo<MockExam, $this> */
    public function mockExam(): BelongsTo
    {
        return $this->belongsTo(MockExam::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deadline(): Carbon
    {
        return $this->started_at->copy()->addMinutes($this->time_limit_minutes);
    }

    public function remainingSeconds(): int
    {
        return max(0, (int) now()->diffInSeconds($this->deadline(), false));
    }
}
