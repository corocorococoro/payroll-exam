<?php

namespace App\Services;

use App\Models\Question;
use App\Models\ReviewItem;
use App\Models\UserQuestionProgress;
use Illuminate\Support\Facades\DB;

class QuestionRevisionService
{
    /**
     * 内容が変わった問題は、旧版で得た習熟判定を引き継がず即日再確認へ戻す。
     */
    public function invalidate(Question $question): void
    {
        DB::transaction(function () use ($question): void {
            $progresses = UserQuestionProgress::query()
                ->where('question_id', $question->id)
                ->where('content_revision_seen', '<>', $question->content_revision)
                ->get(['user_id']);

            if ($progresses->isEmpty()) {
                return;
            }

            $userIds = $progresses->pluck('user_id');
            UserQuestionProgress::query()
                ->where('question_id', $question->id)
                ->whereIn('user_id', $userIds)
                ->update([
                    'state' => 'learning',
                    'box' => 1,
                    'due_at' => now(),
                    'first_seen_at' => null,
                    'updated_at' => now(),
                ]);

            $existingLapses = ReviewItem::query()
                ->where('question_id', $question->id)
                ->whereIn('user_id', $userIds)
                ->pluck('lapses', 'user_id');
            $now = now();
            $rows = $userIds->map(fn (int $userId): array => [
                'user_id' => $userId,
                'question_id' => $question->id,
                'box' => 1,
                'due_date' => today(),
                'lapses' => (int) ($existingLapses[$userId] ?? 0),
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            ReviewItem::query()->upsert(
                $rows,
                ['user_id', 'question_id'],
                ['box', 'due_date', 'updated_at'],
            );
        });
    }
}
