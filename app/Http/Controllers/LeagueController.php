<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\LeagueScore;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeagueController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $scores = LeagueScore::with('user:id,name,avatar')
            ->whereDate('week_start', today()->startOfWeek())
            ->orderByDesc('xp')
            ->limit(50)
            ->get();

        $badges = Badge::orderBy('sort_order')->get();
        $earned = $request->user()->badges()->pluck('badges.id')->all();

        return Inertia::render('league/Index', [
            'leaderboard' => $scores->map(fn (LeagueScore $score, int $index) => [
                'rank' => $index + 1,
                'name' => $score->user->name,
                'avatar' => $score->user->avatar,
                'xp' => $score->xp,
                'is_me' => $score->user_id === $request->user()->id,
            ]),
            'badges' => $badges->map(fn (Badge $badge) => [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'icon' => $badge->icon,
                'earned' => in_array($badge->id, $earned, true),
            ]),
            'week_label' => today()->startOfWeek()->format('n/j').'〜'.today()->endOfWeek()->format('n/j'),
        ]);
    }
}
