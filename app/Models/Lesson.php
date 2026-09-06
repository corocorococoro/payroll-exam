<?php

namespace App\Models;

use App\Services\QuestionDependencyReviewService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['unit_id', 'slug', 'name', 'description', 'study_guide', 'sort_order'])]
class Lesson extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Lesson $lesson): void {
            if ($lesson->wasChanged(['unit_id', 'slug', 'name', 'description', 'study_guide'])) {
                app(QuestionDependencyReviewService::class)->invalidate(Question::query()->where('lesson_id', $lesson->id));
            }
        });
    }

    protected function casts(): array
    {
        return [
            'study_guide' => 'array',
        ];
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->published();
    }
}
