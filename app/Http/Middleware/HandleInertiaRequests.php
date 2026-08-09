<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'stats' => fn () => $this->stats($request),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * 学習統計のヘッダー表示用共有データ。
     *
     * @return array<string, mixed>|null
     */
    private function stats(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        $stat = $user->statOrCreate();
        $activity = $user->dailyActivities()->whereDate('date', today())->first();

        return [
            'total_xp' => $stat->total_xp,
            'current_streak' => $stat->current_streak,
            'streak_freezes' => $stat->streak_freezes,
            'today_xp' => $activity->xp ?? 0,
            'goal_met' => $activity->goal_met ?? false,
            'daily_goal' => $user->daily_goal,
            'exam_date' => $user->exam_date?->toDateString() ?? '2026-11-22',
            'days_to_exam' => (int) today()->diffInDays($user->exam_date ?? '2026-11-22', false),
        ];
    }
}
