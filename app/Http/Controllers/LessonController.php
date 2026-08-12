<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\ReferenceSheet;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\DailyQuestService;
use App\Services\LessonRunService;
use App\Services\XpLevelService;
use App\Services\XpService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    private const int COMPLETION_BONUS_XP = 10;

    /**
     * レッスンプレイヤー。問題配信に正解・解説は含めない（サーバー側判定）。
     */
    public function show(Request $request, Lesson $lesson, LessonRunService $runs): Response
    {
        abort_unless($runs->isUnlocked($request->user(), $lesson), 403, '前のレッスンをクリアすると開放されます。');

        $lesson->load('unit');
        $run = $runs->getOrStart($request, $lesson);
        abort_if($run['question_ids'] === [], 404, 'このレッスンには有効な問題がありません。');

        $questions = $lesson->questions()
            ->whereIn('id', $run['question_ids'])
            ->get()
            ->sortBy(fn ($question) => array_search($question->id, $run['question_ids'], true))
            ->values();

        $sheetSlugs = $questions->pluck('reference_sheet_slugs')->flatten()->filter()->unique()->values();

        $sheets = ReferenceSheet::whereIn('slug', $sheetSlugs)
            ->where('fiscal_year', 2026)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'content']);

        return Inertia::render('learn/Lesson', [
            'lesson' => [
                'id' => $lesson->id,
                'name' => $lesson->name,
                'unit_name' => $lesson->unit->name,
                'unit_color' => $lesson->unit->color,
            ],
            'questions' => $questions->map(fn ($q) => [
                'id' => $q->id,
                'type' => $q->type,
                'question_text' => $q->question_text,
                'choices' => $q->choices,
                'is_calculation' => $q->isCalculation(),
                'reference_sheet_slugs' => $q->reference_sheet_slugs ?? [],
            ]),
            'reference_sheets' => $sheets,
        ]);
    }

    /**
     * レッスン完了: クラウンを上げ、完了ボーナスXPを付与する。
     * 今日そのレッスンの問題数以上の解答記録があることをサーバー側で確認する。
     */
    public function complete(
        Request $request,
        Lesson $lesson,
        LessonRunService $runs,
        XpService $xp,
        XpLevelService $levels,
        DailyQuestService $quests,
        AchievementService $achievements,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($runs->isUnlocked($user, $lesson), 403);

        $run = $runs->current($request, $lesson);

        if ($run === null || $run['question_ids'] === []) {
            return response()->json(['message' => '有効なレッスンセッションがありません。'], 422);
        }

        $runStartedAt = CarbonImmutable::parse($run['started_at']);

        $result = DB::transaction(function () use ($user, $lesson, $run, $runStartedAt, $xp, $levels, $quests, $achievements): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $beforeXp = $user->statOrCreate()->total_xp;

            $answeredCount = $user->attempts()
                ->where('lesson_id', $lesson->id)
                ->where('context', 'lesson')
                ->where('created_at', '>=', $runStartedAt)
                ->whereIn('question_id', $run['question_ids'])
                ->distinct()
                ->count('question_id');

            if ($answeredCount < count($run['question_ids'])) {
                abort(422, 'レッスンが完了していません。');
            }

            $progress = $user->lessonProgresses()->firstOrCreate(
                ['lesson_id' => $lesson->id],
                ['crown_level' => 0, 'completed_count' => 0],
            );

            if ($progress->last_completed_at?->greaterThanOrEqualTo($runStartedAt)) {
                abort(422, 'このレッスンセッションは完了済みです。');
            }

            $crownIncreased = $progress->crown_level < LessonProgress::MAX_CROWN;
            $nextCrown = $crownIncreased ? $progress->crown_level + 1 : $progress->crown_level;

            $progress->update([
                'crown_level' => $nextCrown,
                'completed_count' => $progress->completed_count + 1,
                'last_completed_at' => now(),
            ]);

            $awards = [];
            $bonusXp = 0;

            if ($crownIncreased) {
                $award = $xp->award(
                    $user,
                    self::COMPLETION_BONUS_XP,
                    'lesson_crown',
                    "lesson-crown:{$lesson->id}:{$nextCrown}",
                );

                if ($award !== null) {
                    $bonusXp = $award['amount'];
                    $awards[] = $award;
                    $awards = [...$awards, ...$quests->recordXp($user, $award['amount'])];
                }
            }

            $awards = [...$awards, ...$quests->recordLessonCompleted($user)];
            $achievements->evaluate($user);
            $levels->syncRewardUnlocks($user);

            return compact('progress', 'crownIncreased', 'bonusXp', 'awards', 'beforeXp');
        });

        $runs->clear($request, $lesson);

        $stat = $user->statOrCreate()->refresh();
        $activity = $user->dailyActivities()->whereDate('date', today())->first();
        $questXp = collect($result['awards'])->where('source_type', 'daily_quest')->sum('amount');

        return response()->json([
            'crown_level' => $result['progress']->crown_level,
            'crown_increased' => $result['crownIncreased'],
            'bonus_xp' => $result['bonusXp'],
            'xp_bonus_earned' => $questXp,
            'xp_total_earned' => $result['bonusXp'] + $questXp,
            'total_xp' => $stat->total_xp,
            'current_streak' => $stat->current_streak,
            'today_xp' => $activity->xp ?? 0,
            'goal_met' => $activity->goal_met ?? false,
            'daily_goal' => $user->daily_goal,
            'xp_progress' => $levels->progress($user),
            'level_ups' => $levels->crossedLevels($result['beforeXp'], $stat->total_xp),
        ]);
    }
}
