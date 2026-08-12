<?php

namespace App\Services;

use App\Models\User;
use App\Models\XpTransaction;

class XpService
{
    /**
     * @return array{amount: int, source_type: string, source_key: string}|null
     */
    public function award(User $user, int $amount, string $sourceType, string $sourceKey): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        $transaction = XpTransaction::firstOrCreate(
            ['user_id' => $user->id, 'source_key' => $sourceKey],
            ['amount' => $amount, 'source_type' => $sourceType],
        );

        if (! $transaction->wasRecentlyCreated) {
            return null;
        }

        $stat = $user->statOrCreate();
        $stat->increment('total_xp', $amount);

        $user->leagueScores()->firstOrCreate(
            ['week_start' => today()->startOfWeek()],
            ['xp' => 0],
        )->increment('xp', $amount);

        $activity = $user->dailyActivities()->firstOrCreate(
            ['date' => today()],
            ['xp' => 0, 'questions_answered' => 0, 'goal_met' => false],
        );
        $activity->increment('xp', $amount);
        $activity->refresh();

        if (! $activity->goal_met && $activity->xp >= (int) ($user->daily_goal ?: 20)) {
            $activity->update(['goal_met' => true]);
            app(StreakService::class)->recordGoalMet($user);
        }

        return [
            'amount' => $amount,
            'source_type' => $sourceType,
            'source_key' => $sourceKey,
        ];
    }
}
