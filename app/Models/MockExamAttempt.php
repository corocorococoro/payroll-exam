<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $mock_exam_id
 * @property int $time_limit_minutes
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $finished_at
 * @property array<int, string>|null $answers
 * @property int|null $score
 * @property array<string, mixed>|null $section_scores
 * @property array<string, mixed>|null $unit_scores
 * @property int|null $knowledge_score
 * @property int|null $calculation_score
 * @property array<int, array<string, mixed>>|null $review_snapshot
 */
#[Fillable([
    'user_id', 'mock_exam_id', 'time_limit_minutes', 'started_at', 'finished_at',
    'answers', 'score', 'section_scores', 'unit_scores', 'knowledge_score', 'calculation_score', 'review_snapshot',
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
            'unit_scores' => 'array',
            'review_snapshot' => 'array',
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

    public function deadline(): CarbonInterface
    {
        return $this->started_at->copy()->addMinutes($this->time_limit_minutes);
    }

    public function remainingSeconds(): int
    {
        return max(0, (int) now()->diffInSeconds($this->deadline(), false));
    }
}
