<?php

namespace App\Services;

use App\Enums\AttemptContext;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\ReviewItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 解答判定はすべてサーバー側で行う（正解のフロント漏洩・XPチート防止）。
 * 判定・記録・XP付与・SRS更新を1トランザクションで処理する。
 */
class AnswerService
{
    /**
     * @return array{correct: bool, correct_answer: string, explanation: string, common_mistake: string|null, xp_earned: int}
     */
    public function answer(User $user, Question $question, string $given, AttemptContext $context, ?int $lessonId = null): array
    {
        return DB::transaction(function () use ($user, $question, $given, $context, $lessonId) {
            $correct = $question->checkAnswer($given);
            $xp = $correct ? $question->difficulty->xp() : 0;

            $user->attempts()->create([
                'question_id' => $question->id,
                'lesson_id' => $lessonId,
                'context' => $context,
                'is_correct' => $correct,
                'given_answer' => ['given' => $given],
                'xp_earned' => $xp,
            ]);

            // 誤答も「解答した問題数」には含める。レッスン完了ボーナスなど、
            // 問題への解答を伴わない XP は呼び出し側で countQuestion=false にする。
            $this->awardXp($user, $xp, countQuestion: true);

            $this->updateReviewItem($user, $question, $correct, $context);
            app(DailyQuestService::class)->recordAnswer($user, $correct, $context);
            app(AchievementService::class)->evaluate($user);

            return [
                'correct' => $correct,
                'correct_answer' => $question->type === QuestionType::Choice
                    ? (string) $question->answer['choice']
                    : number_format((float) $question->answer['value']),
                'explanation' => $question->explanation,
                'common_mistake' => $question->common_mistake,
                'xp_earned' => $xp,
            ];
        });
    }

    public function awardXp(User $user, int $xp, bool $countQuestion = false, bool $trackQuests = true): void
    {
        $stat = $user->statOrCreate();

        if ($xp > 0) {
            $stat->increment('total_xp', $xp);

            $user->leagueScores()->firstOrCreate(
                ['week_start' => today()->startOfWeek()],
                ['xp' => 0],
            )->increment('xp', $xp);
        }

        $activity = $user->dailyActivities()->firstOrCreate(
            ['date' => today()],
            ['xp' => 0, 'questions_answered' => 0, 'goal_met' => false],
        );

        if ($xp > 0) {
            $activity->increment('xp', $xp);
        }

        if ($countQuestion) {
            $activity->increment('questions_answered');
        }

        if (! $activity->goal_met && $activity->xp >= $user->daily_goal) {
            $activity->update(['goal_met' => true]);
            app(StreakService::class)->recordGoalMet($user);
        }

        if ($trackQuests) {
            app(DailyQuestService::class)->recordXp($user, $xp);
        }
    }

    /**
     * Leitner 方式: 誤答→box1で明日復習へ。復習で正解→box+1（間隔延長）、最大到達で卒業。
     */
    private function updateReviewItem(User $user, Question $question, bool $correct, AttemptContext $context): void
    {
        $item = $user->reviewItems()->where('question_id', $question->id)->first();

        if (! $correct) {
            if ($item === null) {
                $user->reviewItems()->create([
                    'question_id' => $question->id,
                    'box' => 1,
                    'due_date' => today()->addDay(),
                    'lapses' => 1,
                ]);
            } else {
                $item->update([
                    'box' => 1,
                    'due_date' => today()->addDay(),
                    'lapses' => $item->lapses + 1,
                ]);
            }

            return;
        }

        if ($item === null || $context !== AttemptContext::Review) {
            return;
        }

        $nextBox = $item->box + 1;

        if ($nextBox > ReviewItem::MAX_BOX) {
            $item->delete();

            return;
        }

        $item->update([
            'box' => $nextBox,
            'due_date' => today()->addDays(ReviewItem::INTERVALS[$nextBox]),
        ]);
    }
}
