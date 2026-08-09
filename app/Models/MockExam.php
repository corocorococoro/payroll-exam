<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'slug', 'name', 'description', 'time_limit_minutes', 'passing_score', 'sort_order'])]
class MockExam extends Model
{
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
}
