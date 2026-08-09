<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\User;

class AchievementService
{
    public function evaluate(User $user): void
    {
        $stat = $user->statOrCreate()->refresh();
        $bestMock = $user->mockExamAttempts()->whereNotNull('finished_at')->max('score') ?? 0;
        $lessonMasters = $user->lessonProgresses()->where('crown_level', '>=', 5)->count();

        $earned = [
            'first-step' => $user->attempts()->exists(),
            'xp-100' => $stat->total_xp >= 100,
            'xp-1000' => $stat->total_xp >= 1000,
            'streak-7' => $stat->current_streak >= 7,
            'streak-30' => $stat->current_streak >= 30,
            'streak-100' => $stat->current_streak >= 100,
            'lesson-master' => $lessonMasters >= 1,
            'mock-pass' => $bestMock >= 70,
            'mock-80' => $bestMock >= 80,
            'mock-90' => $bestMock >= 90,
        ];

        $badgeIds = Badge::whereIn('slug', array_keys(array_filter($earned)))->pluck('id');

        foreach ($badgeIds as $badgeId) {
            $user->badges()->syncWithoutDetaching([$badgeId => ['awarded_at' => now()]]);
        }
    }
}
