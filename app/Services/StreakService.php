<?php

namespace App\Services;

use App\Models\User;

/**
 * ストリーク（連続学習日数）の管理。日付判定はすべて JST（アプリのタイムゾーン）。
 */
class StreakService
{
    public const int MAX_FREEZES = 2;

    /**
     * デイリーゴール達成時に呼ばれ、ストリークを伸ばす。
     */
    public function recordGoalMet(User $user): void
    {
        $stat = $user->statOrCreate();
        $today = today();

        if ($stat->last_active_date?->isSameDay($today)) {
            return;
        }

        $streak = $stat->last_active_date?->isSameDay($today->copy()->subDay())
            ? $stat->current_streak + 1
            : 1;

        $stat->update([
            'current_streak' => $streak,
            'longest_streak' => max($streak, $stat->longest_streak),
            'last_active_date' => $today,
        ]);
    }

    /**
     * 深夜0時のスケジューラから呼ばれる。前日にゴール未達なら、
     * フリーズがあれば消費してストリーク維持、なければリセットする。
     */
    public function applyOvernightCheck(User $user): void
    {
        $stat = $user->statOrCreate();
        $yesterday = today()->subDay();

        if ($stat->current_streak === 0 || $stat->last_active_date === null) {
            return;
        }

        // 昨日以降に活動があればなにもしない
        if ($stat->last_active_date->gte($yesterday)) {
            return;
        }

        if ($stat->streak_freezes > 0) {
            // フリーズを1つ消費してストリークを維持（last_active_date を昨日扱いにする）
            $stat->update([
                'streak_freezes' => $stat->streak_freezes - 1,
                'last_active_date' => $yesterday,
            ]);

            return;
        }

        $stat->update(['current_streak' => 0]);
    }

    /** 毎週月曜にフリーズを1つ補充する（所持上限2個）。 */
    public function grantWeeklyFreeze(User $user): void
    {
        $stat = $user->statOrCreate();

        if ($stat->streak_freezes < self::MAX_FREEZES) {
            $stat->increment('streak_freezes');
        }
    }
}
