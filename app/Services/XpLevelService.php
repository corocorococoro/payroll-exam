<?php

namespace App\Services;

use App\Models\User;

class XpLevelService
{
    /**
     * @return list<array{
     *     level: int,
     *     threshold: int,
     *     title: string,
     *     message: string,
     *     style: string|null,
     *     style_name: string|null
     * }>
     */
    public function levels(): array
    {
        return array_values(array_map(
            static fn (array $level): array => [
                'level' => (int) $level['level'],
                'threshold' => (int) $level['threshold'],
                'title' => (string) $level['title'],
                'message' => (string) $level['message'],
                'style' => isset($level['style']) ? (string) $level['style'] : null,
                'style_name' => isset($level['style_name']) ? (string) $level['style_name'] : null,
            ],
            config('xp.levels', []),
        ));
    }

    /**
     * @return array{
     *     total_xp: int,
     *     level: int,
     *     title: string,
     *     level_start_xp: int,
     *     next_level_xp: int|null,
     *     xp_to_next: int|null,
     *     progress_percent: int,
     *     mascot_style: string,
     *     today_xp: int,
     *     daily_goal: int,
     *     goal_met: bool,
     *     current_streak: int
     * }
     */
    public function progress(User $user): array
    {
        $stat = $user->statOrCreate()->refresh();
        $activity = $user->dailyActivities()->whereDate('date', today())->first();
        $levels = $this->levels();
        $current = $levels[0];
        $next = null;

        foreach ($levels as $index => $level) {
            if ($stat->total_xp >= $level['threshold']) {
                $current = $level;
                $next = $levels[$index + 1] ?? null;
            }
        }

        $progress = $next === null
            ? 100
            : (int) floor(
                (($stat->total_xp - $current['threshold']) / ($next['threshold'] - $current['threshold'])) * 100,
            );
        $mascotStyle = $this->isStyleUnlocked($stat->mascot_style, $stat->total_xp)
            ? $stat->mascot_style
            : 'default';

        return [
            'total_xp' => $stat->total_xp,
            'level' => $current['level'],
            'title' => $current['title'],
            'level_start_xp' => $current['threshold'],
            'next_level_xp' => $next['threshold'] ?? null,
            'xp_to_next' => $next === null ? null : max(0, $next['threshold'] - $stat->total_xp),
            'progress_percent' => max(0, min(100, $progress)),
            'mascot_style' => $mascotStyle,
            'today_xp' => $activity->xp ?? 0,
            'daily_goal' => (int) ($user->daily_goal ?: 20),
            'goal_met' => $activity->goal_met ?? false,
            'current_streak' => $stat->current_streak,
        ];
    }

    /**
     * @return list<array{
     *     level: int,
     *     threshold: int,
     *     title: string,
     *     message: string,
     *     style: string|null,
     *     style_name: string|null
     * }>
     */
    public function crossedLevels(int $beforeXp, int $afterXp): array
    {
        return array_values(array_filter(
            $this->levels(),
            fn (array $level): bool => $level['threshold'] > $beforeXp && $level['threshold'] <= $afterXp,
        ));
    }

    /**
     * @return list<array{
     *     slug: string,
     *     name: string,
     *     level: int,
     *     threshold: int
     * }>
     */
    public function styleCatalog(): array
    {
        return array_values(array_map(
            static fn (array $style): array => [
                'slug' => (string) $style['slug'],
                'name' => (string) $style['name'],
                'level' => (int) $style['level'],
                'threshold' => (int) $style['threshold'],
            ],
            config('xp.styles', []),
        ));
    }

    /** @return list<string> */
    public function syncRewardUnlocks(User $user): array
    {
        $stat = $user->statOrCreate()->refresh();
        $totalXp = $stat->total_xp;
        $styles = collect($this->styleCatalog());
        $unlocked = [];

        $lockedSlugs = $styles
            ->filter(fn (array $style): bool => $style['slug'] !== 'default' && $style['threshold'] > $totalXp)
            ->pluck('slug');
        $user->rewardUnlocks()->whereIn('reward_slug', $lockedSlugs)->delete();

        if (! $this->isStyleUnlocked($stat->mascot_style, $totalXp)) {
            $stat->update(['mascot_style' => 'default']);
        }

        foreach ($styles as $style) {
            $slug = $style['slug'];

            if ($slug === 'default' || $style['threshold'] > $totalXp) {
                continue;
            }

            $reward = $user->rewardUnlocks()->firstOrCreate(
                ['reward_slug' => $slug],
                ['unlocked_at' => now()],
            );

            if ($reward->wasRecentlyCreated) {
                $unlocked[] = $slug;
            }
        }

        return $unlocked;
    }

    /** @return list<array<string, int|string|bool>> */
    public function styles(User $user): array
    {
        $stat = $user->statOrCreate()->refresh();
        $equipped = $this->isStyleUnlocked($stat->mascot_style, $stat->total_xp)
            ? $stat->mascot_style
            : 'default';

        return array_map(function (array $style) use ($stat, $equipped): array {
            $slug = $style['slug'];

            return [
                'slug' => $slug,
                'name' => $style['name'],
                'level' => $style['level'],
                'threshold' => $style['threshold'],
                'unlocked' => $this->isStyleUnlocked($slug, $stat->total_xp),
                'equipped' => $slug === $equipped,
            ];
        }, $this->styleCatalog());
    }

    public function canEquip(User $user, string $slug): bool
    {
        $known = collect($this->styles($user))->firstWhere('slug', $slug);

        return $known !== null && $known['unlocked'];
    }

    private function isStyleUnlocked(string $slug, int $totalXp): bool
    {
        $style = collect($this->styleCatalog())->firstWhere('slug', $slug);

        return $style !== null && ($slug === 'default' || $style['threshold'] <= $totalXp);
    }
}
