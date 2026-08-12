<?php

namespace App\Services;

use App\Enums\AttemptContext;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\ReviewItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 解答判定はすべてサーバー側で行う（正解のフロント漏洩・XPチート防止）。
 * 判定・記録・XP付与・SRS更新を1トランザクションで処理する。
 */
class AnswerService
{
    /**
     * @return array<string, mixed>
     */
    public function answer(
        User $user,
        Question $question,
        string $given,
        AttemptContext $context,
        ?int $lessonId = null,
        ?CarbonImmutable $runStartedAt = null,
    ): array {
        return DB::transaction(function () use ($user, $question, $given, $context, $lessonId, $runStartedAt) {
            // 同一ユーザーの採点処理を直列化し、二重クリックや通信再送でも
            // XP・クエスト・復習状態を二重更新しない。
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($context === AttemptContext::Lesson) {
                if ($lessonId === null || $runStartedAt === null) {
                    throw ValidationException::withMessages([
                        'question_id' => '有効なレッスンセッションがありません。',
                    ]);
                }

                $alreadyAnswered = $user->attempts()
                    ->where('question_id', $question->id)
                    ->where('lesson_id', $lessonId)
                    ->where('context', AttemptContext::Lesson)
                    ->where('created_at', '>=', $runStartedAt)
                    ->exists();

                if ($alreadyAnswered) {
                    throw ValidationException::withMessages([
                        'question_id' => 'この問題にはすでに解答済みです。',
                    ]);
                }
            } else {
                $isDue = $user->reviewItems()
                    ->where('question_id', $question->id)
                    ->whereDate('due_date', '<=', today())
                    ->lockForUpdate()
                    ->exists();

                if (! $isDue) {
                    throw ValidationException::withMessages([
                        'question_id' => 'この問題は現在の復習対象ではありません。',
                    ]);
                }
            }

            $beforeXp = $user->statOrCreate()->total_xp;
            $correct = $question->checkAnswer($given);
            $hasPriorCorrect = $user->attempts()
                ->where('question_id', $question->id)
                ->where('is_correct', true)
                ->exists();
            $xp = $correct && ($context === AttemptContext::Review || ! $hasPriorCorrect)
                ? $question->difficulty->xp()
                : 0;

            $attempt = $user->attempts()->create([
                'question_id' => $question->id,
                'lesson_id' => $lessonId,
                'context' => $context,
                'is_correct' => $correct,
                'given_answer' => ['given' => $given],
                'xp_earned' => $xp,
            ]);

            $activity = $user->dailyActivities()->firstOrCreate(
                ['date' => today()],
                ['xp' => 0, 'questions_answered' => 0, 'goal_met' => false],
            );
            $activity->update([
                'questions_answered' => $user->attempts()
                    ->whereDate('created_at', today())
                    ->distinct()
                    ->count('question_id'),
            ]);

            $awards = [];
            $directAward = app(XpService::class)->award(
                $user,
                $xp,
                $context === AttemptContext::Review ? 'review' : 'question',
                ($context === AttemptContext::Review ? 'review:' : 'question:').$attempt->id,
            );

            if ($directAward !== null) {
                $awards[] = $directAward;
                $awards = [...$awards, ...app(DailyQuestService::class)->recordXp($user, $directAward['amount'])];
            }

            $this->updateReviewItem($user, $question, $correct, $context);
            $awards = [...$awards, ...app(DailyQuestService::class)->recordAnswer($user, $context)];
            app(AchievementService::class)->evaluate($user);
            app(XpLevelService::class)->syncRewardUnlocks($user);

            $afterXp = $user->statOrCreate()->refresh()->total_xp;
            $questXp = collect($awards)
                ->where('source_type', 'daily_quest')
                ->sum('amount');

            return [
                'correct' => $correct,
                'correct_answer' => $question->type === QuestionType::Choice
                    ? (string) $question->answer['choice']
                    : number_format((float) $question->answer['value']),
                'explanation' => $question->explanation,
                'common_mistake' => $question->common_mistake,
                'selected_feedback' => $correct
                    ? null
                    : ($question->distractor_feedback[strtoupper(trim($given))] ?? null),
                'xp_status' => ! $correct ? 'incorrect' : ($xp > 0 ? 'earned' : 'already_credited'),
                'xp_earned' => $xp,
                'xp_bonus_earned' => $questXp,
                'xp_total_earned' => $xp + $questXp,
                'xp_progress' => app(XpLevelService::class)->progress($user),
                'level_ups' => app(XpLevelService::class)->crossedLevels($beforeXp, $afterXp),
            ];
        });
    }

    /**
     * Leitner 方式: 誤答→box1で今日の復習へ。復習で正解→box+1（間隔延長）、最大到達で卒業。
     */
    private function updateReviewItem(User $user, Question $question, bool $correct, AttemptContext $context): void
    {
        $item = $user->reviewItems()->where('question_id', $question->id)->first();

        if (! $correct) {
            if ($item === null) {
                $user->reviewItems()->create([
                    'question_id' => $question->id,
                    'box' => 1,
                    'due_date' => today(),
                    'lapses' => 1,
                ]);
            } else {
                $item->update([
                    'box' => 1,
                    'due_date' => today(),
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
