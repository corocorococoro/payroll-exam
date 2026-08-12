<?php

namespace App\Services;

use App\Enums\AttemptContext;
use App\Models\DailyQuest;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DailyQuestService
{
    /** @return Collection<int, DailyQuest> */
    public function ensureToday(User $user): Collection
    {
        if (! $user->dailyQuests()->whereDate('date', today())->exists()) {
            $reviewDue = $user->reviewItems()
                ->whereDate('due_date', '<=', today())
                ->whereHas('question', function (Builder $query): void {
                    /** @var Builder<Question> $query */
                    $query->published();
                })
                ->count();

            $thirdQuest = $reviewDue > 0
                ? ['quest_type' => 'review_correct', 'target' => min(3, $reviewDue), 'xp_reward' => 15]
                : ['quest_type' => 'complete_lesson', 'target' => 1, 'xp_reward' => 15];

            $definitions = [
                ['quest_type' => 'earn_xp', 'target' => (int) ($user->daily_goal ?: 20), 'xp_reward' => 10],
                ['quest_type' => 'answer_questions', 'target' => 5, 'xp_reward' => 10],
                $thirdQuest,
            ];

            foreach ($definitions as $definition) {
                $user->dailyQuests()->create([
                    ...$definition,
                    'date' => today(),
                    'progress' => 0,
                    'completed' => false,
                ]);
            }
        }

        return $user->dailyQuests()->whereDate('date', today())->orderBy('id')->get();
    }

    /** @return list<array{amount: int, source_type: string, source_key: string}> */
    public function recordXp(User $user, int $xp): array
    {
        return $xp > 0 ? $this->advanceBy($user, 'earn_xp', $xp, feedEarnXp: false) : [];
    }

    /** @return list<array{amount: int, source_type: string, source_key: string}> */
    public function recordAnswer(User $user, AttemptContext $context): array
    {
        $awards = [];
        $answered = $user->attempts()
            ->whereDate('created_at', today())
            ->distinct()
            ->count('question_id');
        $awards = [...$awards, ...$this->advanceTo($user, 'answer_questions', $answered)];

        if ($context === AttemptContext::Review) {
            $reviewCorrect = $user->attempts()
                ->whereDate('created_at', today())
                ->where('context', AttemptContext::Review)
                ->where('is_correct', true)
                ->distinct()
                ->count('question_id');
            $awards = [...$awards, ...$this->advanceTo($user, 'review_correct', $reviewCorrect)];
        }

        return $awards;
    }

    /** @return list<array{amount: int, source_type: string, source_key: string}> */
    public function recordLessonCompleted(User $user): array
    {
        return $this->advanceTo($user, 'complete_lesson', 1);
    }

    /** @return list<array{amount: int, source_type: string, source_key: string}> */
    private function advanceBy(User $user, string $type, int $amount, bool $feedEarnXp = true): array
    {
        $quest = $this->ensureToday($user)->firstWhere('quest_type', $type);

        if ($quest === null || $quest->completed) {
            return [];
        }

        return $this->completeIfReached($user, $quest, min($quest->target, $quest->progress + $amount), $feedEarnXp);
    }

    /** @return list<array{amount: int, source_type: string, source_key: string}> */
    private function advanceTo(User $user, string $type, int $progress): array
    {
        $quest = $this->ensureToday($user)->firstWhere('quest_type', $type);

        if ($quest === null || $quest->completed) {
            return [];
        }

        return $this->completeIfReached($user, $quest, min($quest->target, $progress), true);
    }

    /** @return list<array{amount: int, source_type: string, source_key: string}> */
    private function completeIfReached(User $user, DailyQuest $quest, int $progress, bool $feedEarnXp): array
    {
        $completed = $progress >= $quest->target;
        $quest->update(['progress' => $progress, 'completed' => $completed]);

        if (! $completed) {
            return [];
        }

        $award = app(XpService::class)->award(
            $user,
            $quest->xp_reward,
            'daily_quest',
            "quest:{$quest->id}",
        );

        if ($award === null) {
            return [];
        }

        $awards = [$award];

        if ($feedEarnXp && $quest->quest_type !== 'earn_xp') {
            $awards = [...$awards, ...$this->recordXp($user, $award['amount'])];
        }

        return $awards;
    }
}
