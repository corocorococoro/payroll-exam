<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'slug', 'name', 'description', 'time_limit_minutes', 'passing_score', 'sort_order', 'is_published'])]
class MockExam extends Model
{
    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<MockExamQuestion, $this> */
    public function examQuestions(): HasMany
    {
        return $this->hasMany(MockExamQuestion::class)->orderBy('position');
    }

    /** @return HasMany<MockExamAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(MockExamAttempt::class);
    }

    public function isAvailableForNewAttempt(): bool
    {
        if (! $this->is_published) {
            return false;
        }

        $questionIds = $this->examQuestions()->pluck('question_id');

        return $questionIds->isNotEmpty()
            && Question::query()->published()->whereIn('id', $questionIds)->count() === $questionIds->count();
    }
}
