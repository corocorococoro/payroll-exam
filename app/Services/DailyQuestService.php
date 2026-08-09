<?php

namespace App\Services;

use App\Enums\AttemptContext;
use App\Models\DailyQuest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class DailyQuestService
{
    /** @return Collection<int, DailyQuest> */
    public function ensureToday(User $user)
    {
        if (! $user->dailyQuests()->whereDate('date', today())->exists()) {
            $definitions = [
                ['quest_type' => 'earn_xp', 'target' => $user->daily_goal, 'xp_reward' => 10],
                ['quest_type' => 'answer_questions', 'target' => 5, 'xp_reward' => 10],
                ['quest_type' => 'review_correct', 'target' => 3, 'xp_reward' => 15],
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

    public function recordXp(User $user, int $xp): void
    {
        if ($xp > 0) {
            $this->advance($user, 'earn_xp', $xp);
        }
    }

    public function recordAnswer(User $user, bool $correct, AttemptContext $context): void
    {
        $this->advance($user, 'answer_questions', 1);

        if ($correct && $context === AttemptContext::Review) {
            $this->advance($user, 'review_correct', 1);
        }
    }

    private function advance(User $user, string $type, int $amount): void
    {
        $quest = $this->ensureToday($user)->firstWhere('quest_type', $type);

        if ($quest === null || $quest->completed) {
            return;
        }

        $progress = min($quest->target, $quest->progress + $amount);
        $completed = $progress >= $quest->target;
        $quest->update(['progress' => $progress, 'completed' => $completed]);

        if ($completed) {
            app(AnswerService::class)->awardXp($user, $quest->xp_reward, trackQuests: false);
        }
    }
}
