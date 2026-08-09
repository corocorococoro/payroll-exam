<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\DailyActivity;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Unit;
use App\Services\DailyQuestService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** 本模試の独自配点。公式の分野別配点ではない。 */
    private const array CATEGORY_WEIGHTS = [
        '労働法・勤怠' => 28,
        '給与基礎・支給控除' => 14,
        '税' => 24,
        '社会保険' => 34,
    ];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user->onboarded) {
            return Inertia::render('Onboarding', [
                'defaults' => [
                    'daily_goal' => $user->daily_goal,
                    'reminder_time' => $user->reminder_time !== null
                        ? substr((string) $user->reminder_time, 0, 5)
                        : '20:00',
                    'reminder_enabled' => $user->reminder_enabled,
                    'exam_date' => $user->exam_date?->toDateString() ?? '2026-11-22',
                ],
            ]);
        }

        $stat = $user->statOrCreate();
        $today = $user->dailyActivities()->whereDate('date', today())->first();
        $course = Course::where('slug', 'kyuyo-2kyu')->firstOrFail();
        $units = $course->units()->get();
        $quests = app(DailyQuestService::class)->ensureToday($user);

        $attempts = QuestionAttempt::query()
            ->where('user_id', $user->id)
            ->with('question:id,unit_id,category')
            ->get();

        // Repeated drills must not make the estimate look artificially certain.
        // Use only the latest result for each unique question.
        $latestAttempts = $attempts->sortByDesc('created_at')->unique('question_id')->values();

        $accuracyByUnit = $units->map(function (Unit $unit) use ($latestAttempts): array {
            $unitAttempts = $latestAttempts->filter(fn (QuestionAttempt $attempt) => $attempt->question?->unit_id === $unit->id);
            $correct = $unitAttempts->where('is_correct', true)->count();

            return [
                'slug' => $unit->slug,
                'name' => $unit->name,
                'icon' => $unit->icon,
                'color' => $unit->color,
                'attempts' => $unitAttempts->count(),
                'correct' => $correct,
                'accuracy' => $unitAttempts->isEmpty() ? 0 : (int) round($correct / $unitAttempts->count() * 100),
            ];
        })->values();

        $estimatedScore = collect(self::CATEGORY_WEIGHTS)->sum(function (int $weight, string $category) use ($latestAttempts): float {
            $categoryAttempts = $latestAttempts->filter(fn (QuestionAttempt $attempt) => $attempt->question?->category === $category);

            if ($categoryAttempts->isEmpty()) {
                return 0;
            }

            return $categoryAttempts->where('is_correct', true)->count() / $categoryAttempts->count() * $weight;
        });

        /** @var Collection<string, DailyActivity> $activities */
        $activities = $user->dailyActivities()
            ->whereDate('date', '>=', today()->subDays(83))
            ->orderBy('date')
            ->get()
            ->keyBy(fn (DailyActivity $activity) => $activity->date->toDateString());

        $heatmap = collect(range(83, 0))->map(function (int $daysAgo) use ($activities): array {
            $date = today()->subDays($daysAgo);
            $activity = $activities->get($date->toDateString());

            return [
                'date' => $date->toDateString(),
                'xp' => $activity === null ? 0 : $activity->xp,
                'goal_met' => $activity === null ? false : $activity->goal_met,
            ];
        });

        return Inertia::render('Dashboard', [
            'summary' => [
                'today_xp' => $today === null ? 0 : $today->xp,
                'daily_goal' => $user->daily_goal,
                'goal_met' => $today === null ? false : $today->goal_met,
                'total_xp' => $stat->total_xp,
                'current_streak' => $stat->current_streak,
                'longest_streak' => $stat->longest_streak,
                'streak_freezes' => $stat->streak_freezes,
                'review_due' => $user->reviewItems()
                    ->whereDate('due_date', '<=', today())
                    ->whereHas('question', function (Builder $query): void {
                        /** @var Builder<Question> $query */
                        $query->published();
                    })
                    ->count(),
                'days_to_exam' => (int) today()->diffInDays($user->exam_date ?? '2026-11-22', false),
                'estimated_score' => (int) round($estimatedScore),
                'score_evidence' => $latestAttempts->count(),
            ],
            'accuracy_by_unit' => $accuracyByUnit,
            'heatmap' => $heatmap,
            'quests' => $quests->map(fn ($quest) => [
                'type' => $quest->quest_type,
                'label' => match ($quest->quest_type) {
                    'earn_xp' => 'XPを獲得しよう',
                    'answer_questions' => '問題に答えよう',
                    'review_correct' => '復習を正解しよう',
                    default => '今日のチャレンジ',
                },
                'target' => $quest->target,
                'progress' => $quest->progress,
                'completed' => $quest->completed,
                'xp_reward' => $quest->xp_reward,
            ]),
            'season' => $this->season(),
        ]);
    }

    /** @return array{current: string, phases: list<array<string, string|bool>>} */
    private function season(): array
    {
        $phases = [
            ['key' => 'foundation', 'label' => '基礎', 'period' => '8/8〜8/31', 'focus' => '労働法・勤怠・給与明細', 'start' => '2026-08-08', 'end' => '2026-08-31'],
            ['key' => 'insurance', 'label' => '社会保険', 'period' => '9/1〜9/27', 'focus' => '標準報酬・定時／随時決定', 'start' => '2026-09-01', 'end' => '2026-09-27'],
            ['key' => 'tax', 'label' => '税・賞与', 'period' => '9/28〜10/18', 'focus' => '所得税・住民税・賞与', 'start' => '2026-09-28', 'end' => '2026-10-18'],
            ['key' => 'mock', 'label' => '実戦', 'period' => '10/19〜11/8', 'focus' => '120分模試と誤答分析', 'start' => '2026-10-19', 'end' => '2026-11-08'],
            ['key' => 'final', 'label' => '直前', 'period' => '11/9〜11/21', 'focus' => '弱点と料率の最終確認', 'start' => '2026-11-09', 'end' => '2026-11-21'],
        ];

        $current = collect($phases)->first(
            fn (array $phase) => today()->betweenIncluded($phase['start'], $phase['end']),
        )['key'] ?? (today()->lt('2026-08-08') ? 'foundation' : 'final');

        return [
            'current' => $current,
            'phases' => array_values(collect($phases)->map(fn (array $phase) => [
                'key' => $phase['key'],
                'label' => $phase['label'],
                'period' => $phase['period'],
                'focus' => $phase['focus'],
                'active' => $phase['key'] === $current,
            ])->all()),
        ];
    }
}
