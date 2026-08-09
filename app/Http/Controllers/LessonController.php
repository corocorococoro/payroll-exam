<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\ReferenceSheet;
use App\Services\AnswerService;
use App\Services\LessonRunService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        AnswerService $answerService,
        LessonRunService $runs,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($runs->isUnlocked($user, $lesson), 403);

        $run = $runs->current($request, $lesson);

        if ($run === null || $run['question_ids'] === []) {
            return response()->json(['message' => '有効なレッスンセッションがありません。'], 422);
        }

        $answeredCount = $user->attempts()
            ->where('lesson_id', $lesson->id)
            ->where('context', 'lesson')
            ->where('created_at', '>=', CarbonImmutable::parse($run['started_at']))
            ->whereIn('question_id', $run['question_ids'])
            ->distinct()
            ->count('question_id');

        if ($answeredCount < count($run['question_ids'])) {
            return response()->json(['message' => 'レッスンが完了していません。'], 422);
        }

        $progress = $user->lessonProgresses()->firstOrCreate(
            ['lesson_id' => $lesson->id],
            ['crown_level' => 0, 'completed_count' => 0],
        );

        $progress->update([
            'crown_level' => min(LessonProgress::MAX_CROWN, $progress->crown_level + 1),
            'completed_count' => $progress->completed_count + 1,
            'last_completed_at' => now(),
        ]);

        $answerService->awardXp($user, self::COMPLETION_BONUS_XP);
        $runs->clear($request, $lesson);

        $stat = $user->statOrCreate()->refresh();
        $activity = $user->dailyActivities()->whereDate('date', today())->first();

        return response()->json([
            'crown_level' => $progress->crown_level,
            'bonus_xp' => self::COMPLETION_BONUS_XP,
            'total_xp' => $stat->total_xp,
            'current_streak' => $stat->current_streak,
            'today_xp' => $activity->xp ?? 0,
            'goal_met' => $activity->goal_met ?? false,
            'daily_goal' => $user->daily_goal,
        ]);
    }
}
