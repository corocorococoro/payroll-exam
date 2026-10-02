<?php

namespace App\Http\Controllers;

use App\Models\LearningModule;
use App\Models\Question;
use App\Models\ReferenceSheet;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\DailyQuestService;
use App\Services\LearningCurriculumService;
use App\Services\OfficialSourceService;
use App\Services\StudyRunService;
use App\Services\XpLevelService;
use App\Services\XpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudyController extends Controller
{
    public function show(Request $request, LearningModule $module, StudyRunService $runs): Response
    {
        abort_unless($module->is_active, 404);
        $run = $runs->getOrStart($request, $module);
        abort_if($run['question_ids'] === [], 404);
        $questions = $module->questions()->published()->whereIn('questions.id', $run['question_ids'])->get()
            ->sortBy(fn (Question $q) => array_search($q->id, $run['question_ids'], true))->values();
        $attempts = $request->user()->attempts()->where('study_run_id', $run['id'])->get();
        $slugs = $questions->pluck('reference_sheet_slugs')->flatten()->filter()->unique();
        $example = $module->example;
        if ($example !== null) {
            $slugs = $slugs->merge($example->reference_sheet_slugs ?? [])->unique();
        }

        return Inertia::render('learn/Lesson', [
            'lesson' => [
                'id' => $module->id, 'name' => $module->name,
                'unit_name' => $module->course->name, 'unit_color' => 'blue',
                'description' => $module->goal,
                'focus_label' => match ($run['phase']) {
                    'guided' => '例を見て練習', 'spaced' => '日を空けて確認', default => '自力で確認',
                },
                'study_guide' => [
                    'why' => $module->why, 'goal' => $module->goal,
                    'key_points' => $module->approach, 'common_traps' => [], 'worked_example' => null,
                ],
            ],
            'study' => [
                'run_id' => $run['id'],
                'slug' => $module->slug, 'phase' => $run['phase'], 'method' => $module->method,
                'memory_tip' => $run['phase'] === 'guided' ? $module->memory_tip : null,
                'example' => $run['phase'] === 'guided' ? $this->example($module) : null,
                'answered_ids' => $attempts->pluck('question_id')->all(),
                'correct_count' => $attempts->where('is_correct', true)->count(),
                'earned_xp' => $attempts->sum('xp_earned'),
            ],
            'questions' => $questions->map(fn (Question $q): array => [
                'id' => $q->id, 'type' => $q->type, 'question_text' => $q->question_text,
                'choices' => $q->choices, 'is_calculation' => $q->isCalculation(),
                'reference_sheet_slugs' => $q->reference_sheet_slugs ?? [],
            ]),
            'reference_sheets' => ReferenceSheet::whereIn('slug', $slugs)->where('fiscal_year', 2026)
                ->orderBy('sort_order')->get(['slug', 'name', 'content']),
        ]);
    }

    public function support(Request $request, LearningModule $module, StudyRunService $runs): JsonResponse
    {
        $validated = $request->validate(['question_id' => ['required', 'integer'], 'study_run_id' => ['required', 'uuid']]);
        $run = $runs->current($request, $module);
        abort_if($run === null || $validated['study_run_id'] !== $run['id'], 422, '学習一覧から開き直してください。');
        $runs->markHint($request, $module, (int) $validated['question_id']);

        return response()->json([
            'approach' => $module->approach, 'memory_tip' => $module->memory_tip,
            'example' => $this->example($module),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function example(LearningModule $module): ?array
    {
        $example = $module->example;
        // Approval and holdout checks apply even when a module was seeded earlier.
        if ($example === null || ! Question::query()->published()->practiceBank()->whereKey($example->id)->exists()) {
            return null;
        }
        $answer = $example->type->value === 'choice'
            ? collect($example->choices)->firstWhere('key', $example->answer['choice'])['text']
            : number_format((float) $example->answer['value']);

        return [
            'question_text' => $example->question_text, 'answer_text' => $answer,
            'explanation' => $example->explanation,
            'official_sources' => app(OfficialSourceService::class)->forQuestion($example),
        ];
    }

    public function complete(
        Request $request, LearningModule $module, StudyRunService $runs,
        LearningCurriculumService $curriculum, XpService $xp, XpLevelService $levels,
        DailyQuestService $quests, AchievementService $achievements,
    ): JsonResponse {
        $run = $runs->current($request, $module);
        $validated = $request->validate(['study_run_id' => ['required', 'uuid']]);
        abort_if($run === null || $run['question_ids'] === [] || $validated['study_run_id'] !== $run['id'], 422, '学習一覧から開き直してください。');
        $user = $request->user();
        $result = DB::transaction(function () use ($user, $module, $run, $curriculum, $xp, $levels, $quests, $achievements): array {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $beforeXp = $user->statOrCreate()->total_xp;
            $attempts = $user->attempts()->where('study_run_id', $run['id'])->whereIn('question_id', $run['question_ids'])->get();
            abort_unless($attempts->pluck('question_id')->unique()->count() === count($run['question_ids']), 422, 'すべて解答してから結果を表示してください。');
            // The session can be resent concurrently; the user lock and unique XP key prevent a second completion.
            $progress = $curriculum->progress($user, $module);
            abort_if(DB::table('study_run_completions')->where('study_run_id', $run['id'])->exists(), 422, 'この学習はすでに完了しています。');
            $independent = $attempts->where('assisted', false)->where('is_correct', true)->count();
            $accuracy = (int) round($independent / count($run['question_ids']) * 100);
            $passed = $run['phase'] !== 'guided' && $accuracy >= 80;
            $retained = $passed && $run['phase'] === 'spaced'
                && $progress->independent_passed_at !== null
                && $progress->independent_passed_at->toDateString() < today()->toDateString();
            $attributes = ['completed_count' => $progress->completed_count + 1];
            if ($run['phase'] === 'guided') {
                $attributes += ['guided_completed_at' => now(), 'needs_support' => false];
            } elseif ($passed) {
                $attributes += [
                    'guided_completed_at' => $progress->guided_completed_at ?? now(),
                    'independent_passed_at' => $progress->independent_passed_at ?? now(),
                    'review_due_at' => today()->addDays($retained ? 7 : 3), 'needs_support' => false,
                ];
                if ($retained) {
                    $attributes['spaced_passed_at'] = now();
                }
            } else {
                $attributes += ['needs_support' => true, 'independent_passed_at' => null,
                    'spaced_passed_at' => null, 'review_due_at' => today()->addDay()];
            }
            $progress->update($attributes);
            $stage = $run['phase'] === 'guided' ? 'guided' : ($retained ? 'spaced' : 'check');
            $awards = [];
            $bonus = 0;
            if ($run['phase'] === 'guided' || $passed) {
                $award = $xp->award($user, 10, 'learning_stage', "study-stage:{$module->slug}:{$module->content_hash}:{$stage}");
                if ($award !== null) {
                    $bonus = $award['amount'];
                    $awards = [$award, ...$quests->recordXp($user, $bonus)];
                }
            }
            // Persist every completion, including unsuccessful checks without XP, for idempotency.
            DB::table('study_run_completions')->insert(['study_run_id' => $run['id'], 'user_id' => $user->id, 'created_at' => now()]);
            $awards = [...$awards, ...$quests->recordLessonCompleted($user)];
            $achievements->evaluate($user);
            $levels->syncRewardUnlocks($user);

            return compact('beforeXp', 'independent', 'accuracy', 'passed', 'retained', 'bonus', 'awards');
        });
        $runs->clear($request, $module);
        $overview = $curriculum->overview($user);
        $next = $run['phase'] === 'guided' || ! $result['passed']
            ? ['href' => "/study/{$module->slug}", 'name' => $module->name]
            : ($overview['next'] ?? ['href' => '/mock-exams', 'name' => '初見の模試']);
        $stat = $user->statOrCreate()->refresh();
        $activity = $user->dailyActivities()->whereDate('date', today())->first();
        $questXp = collect($result['awards'])->where('source_type', 'daily_quest')->sum('amount');

        return response()->json([
            'crown_level' => 0, 'crown_increased' => false,
            'bonus_xp' => $result['bonus'], 'xp_bonus_earned' => $questXp,
            'xp_total_earned' => $result['bonus'] + $questXp, 'total_xp' => $stat->total_xp,
            'current_streak' => $stat->current_streak, 'today_xp' => $activity->xp ?? 0,
            'goal_met' => $activity->goal_met ?? false, 'daily_goal' => $user->daily_goal,
            'xp_progress' => $levels->progress($user), 'level_ups' => $levels->crossedLevels($result['beforeXp'], $stat->total_xp),
            'study_result' => [
                'phase' => $run['phase'], 'passed' => $result['passed'], 'retained' => $result['retained'],
                'independent_correct_count' => $result['independent'], 'independent_accuracy' => $result['accuracy'],
                'question_count' => count($run['question_ids']), 'next_href' => $next['href'], 'next_name' => $next['name'],
                'review_due_at' => $curriculum->progress($user, $module)->review_due_at?->toDateString(),
            ],
        ]);
    }
}
