<?php

namespace App\Services;

use App\Enums\QuestionReviewStatus;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;

class QuestionDependencyReviewService
{
    /** @param Builder<Question> $questions */
    public function invalidate(Builder $questions): void
    {
        // Update each model so the existing mastery invalidation event runs.
        foreach ($questions->get() as $question) {
            $question->update([
                'content_revision' => $question->content_revision + 1,
                'review_status' => $question->review_status === QuestionReviewStatus::Retired
                    ? QuestionReviewStatus::Retired : QuestionReviewStatus::InReview,
                'review_fingerprint' => null,
                'reviewed_at' => null,
                'review_due_at' => null,
                'is_active' => false,
            ]);
        }
    }
}
