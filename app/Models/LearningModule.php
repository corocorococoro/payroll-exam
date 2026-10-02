<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $slug
 * @property string $content_hash
 * @property array<int, string> $approach
 * @property array<int, string> $prerequisites
 */
#[Fillable(['course_id', 'slug', 'name', 'section', 'goal', 'why', 'method', 'memory_tip', 'approach', 'prerequisites', 'example_question_id', 'position', 'content_hash', 'is_active'])]
class LearningModule extends Model
{
    protected function casts(): array
    {
        return ['approach' => 'array', 'prerequisites' => 'array', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function example(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'example_question_id');
    }

    /** @return BelongsToMany<Question, $this> */
    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class)->withPivot('position')->orderByPivot('position');
    }
}
