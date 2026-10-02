<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $content_hash
 * @property Carbon|null $guided_completed_at
 * @property Carbon|null $independent_passed_at
 * @property Carbon|null $spaced_passed_at
 * @property Carbon|null $review_due_at
 * @property bool $needs_support
 * @property int $completed_count
 */
#[Fillable(['user_id', 'learning_module_id', 'content_hash', 'guided_completed_at', 'independent_passed_at', 'spaced_passed_at', 'review_due_at', 'needs_support', 'completed_count'])]
class LearningModuleProgress extends Model
{
    protected $table = 'learning_module_progress';

    protected function casts(): array
    {
        return [
            'guided_completed_at' => 'datetime', 'independent_passed_at' => 'datetime',
            'spaced_passed_at' => 'datetime', 'review_due_at' => 'datetime', 'needs_support' => 'boolean',
        ];
    }
}
