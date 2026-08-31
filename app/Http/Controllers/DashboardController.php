<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\DailyActivity;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Unit;
use App\Services\DailyQuestService;
use App\Services\XpLevelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
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

        $questionProgresses = $user->questionProgresses()->get();
        $progressByQuestion = $questionProgresses->keyBy('question_id');
        $totalQuestions = Question::query()->published()->count();
        $seenQuestions = $questionProgresses->whereNotNull('first_seen_at')->count();
        $masteredQuestions = $questionProgresses->where('state', 'mastered')->count();
        $unseenQuestions = max(0, $totalQuestions - $seenQuestions);
        $coreQuestionIds = Question::query()->published()->where('study_tier', 'core')->pluck('id');
        $coreProgresses = $questionProgresses->whereIn('question_id', $coreQuestionIds);
        $coreQuestionCount = $coreQuestionIds->count();
        $coreSeenQuestions = $coreProgresses->whereNotNull('first_seen_at')->count();
        $coreMasteredQuestions = $coreProgresses->where('state', 'mastered')->count();
        $coreUnseenQuestions = max(0, $coreQuestionCount - $coreSeenQuestions);
        $dailyNewTarget = min(10, $coreUnseenQuestions > 0 ? $coreUnseenQuestions : $unseenQuestions);
        $firstSeenToday = $questionProgresses
            ->filter(fn ($progress) => $progress->first_seen_at?->isToday() ?? false);
        $newCompletedToday = $coreUnseenQuestions > 0
            ? $firstSeenToday->whereIn('question_id', $coreQuestionIds)->count()
            : $firstSeenToday->count();
        $newCompletedToday = min($dailyNewTarget, $newCompletedToday);
        $dailyNewLabel = $coreUnseenQuestions > 0 ? '今日のコア' : '今日の新規';
        $reviewDue = $user->reviewItems()
            ->whereDate('due_date', '<=', today())
            ->whereHas('question', function (Builder $query): void {
                /** @var Builder<Question> $query */
                $query->published();
            })
            ->count();

        $allLessons = $units->flatMap(fn (Unit $unit) => $unit->lessons)->values();

        $needsRecovery = function (Lesson $lesson) use ($progressByQuestion): bool {
            return $lesson->questions()->published()->pluck('id')->contains(function (int $questionId) use ($progressByQuestion): bool {
                $progress = $progressByQuestion->get($questionId);

                return $progress !== null && (
                    $progress->state === 'learning'
                    || ($progress->due_at?->isPast() ?? false)
                );
            });
        };
        $hasUnseen = function (Lesson $lesson, ?string $tier = null) use ($progressByQuestion): bool {
            $query = $lesson->questions()->published();
            if ($tier !== null) {
                $query->where('study_tier', $tier);
            }

            return $query->pluck('id')->contains(
                fn (int $questionId): bool => ! $progressByQuestion->has($questionId),
            );
        };

        $recommendedLesson = $allLessons->first($needsRecovery);
        $recommendationKind = $recommendedLesson === null ? null : 'recovery';
        if ($recommendedLesson === null) {
            $recommendedLesson = $allLessons->first(fn (Lesson $lesson): bool => $hasUnseen($lesson, 'core'));
            $recommendationKind = $recommendedLesson === null ? null : 'core';
        }
        if ($recommendedLesson === null) {
            $recommendedLesson = $allLessons->first(fn (Lesson $lesson): bool => $hasUnseen($lesson));
            $recommendationKind = $recommendedLesson === null ? null : 'reinforcement';
        }

        $latestMockScore = $user->mockExamAttempts()
            ->whereNotNull('finished_at')
            ->latest('finished_at')
            ->value('score');
        [$readinessLabel, $readinessDetail] = match (true) {
            $latestMockScore !== null && $latestMockScore >= 70 => ['合格ライン到達', '模試70点以上。弱点復習で再現性を高める'],
            $latestMockScore !== null => ['弱点補強中', "直近模試{$latestMockScore}点。誤答分野を優先する"],
            $coreSeenQuestions < (int) ceil($coreQuestionCount * 0.6) => ['基礎構築中', 'まず合格コアを広く一周する'],
            default => ['模試で確認', '合格コアを進めたら模試で70点との差を測る'],
        };

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
                'review_due' => $reviewDue,
                'days_to_exam' => (int) today()->diffInDays($user->exam_date ?? '2026-11-22', false),
                'total_questions' => $totalQuestions,
                'seen_questions' => $seenQuestions,
                'mastered_questions' => $masteredQuestions,
                'coverage_percent' => $totalQuestions === 0 ? 0 : (int) round($seenQuestions / $totalQuestions * 100),
                'mastery_percent' => $totalQuestions === 0 ? 0 : (int) round($masteredQuestions / $totalQuestions * 100),
                'core_question_count' => $coreQuestionCount,
                'core_seen_questions' => $coreSeenQuestions,
                'core_mastered_questions' => $coreMasteredQuestions,
                'core_coverage_percent' => $coreQuestionCount === 0 ? 0 : (int) round($coreSeenQuestions / $coreQuestionCount * 100),
                'core_mastery_percent' => $coreQuestionCount === 0 ? 0 : (int) round($coreMasteredQuestions / $coreQuestionCount * 100),
                'latest_mock_score' => $latestMockScore,
                'readiness_label' => $readinessLabel,
                'readiness_detail' => $readinessDetail,
                'daily_new_target' => $dailyNewTarget,
                'daily_new_label' => $dailyNewLabel,
                'new_completed_today' => $newCompletedToday,
                'recommended_lesson_id' => $recommendedLesson?->id,
                'recommended_lesson_name' => $recommendedLesson?->name,
                'next_action_href' => $reviewDue > 0
                    ? '/review'
                    : ($recommendedLesson === null ? '/learn' : "/lessons/{$recommendedLesson->id}"),
                'next_action_label' => $reviewDue > 0
                    ? "期限到来の復習{$reviewDue}問をはじめる"
                    : match ($recommendationKind) {
                        'recovery' => "「{$recommendedLesson->name}」の弱点を補強する",
                        'core' => "「{$recommendedLesson->name}」の合格コアを進める",
                        'reinforcement' => "「{$recommendedLesson->name}」を補強する",
                        default => '学習一覧を見る',
                    },
                'xp_progress' => app(XpLevelService::class)->progress($user),
            ],
            'accuracy_by_unit' => $accuracyByUnit,
            'heatmap' => $heatmap,
            'quests' => $quests->map(fn ($quest) => [
                'type' => $quest->quest_type,
                'label' => match ($quest->quest_type) {
                    'earn_xp' => 'XPを獲得しよう',
                    'answer_questions' => '問題に答えよう',
                    'review_correct' => '復習を正解しよう',
                    'complete_lesson' => 'レッスンを完了しよう',
                    default => '今日のチャレンジ',
                },
                'target' => $quest->target,
                'progress' => $quest->progress,
                'completed' => $quest->completed,
                'xp_reward' => $quest->xp_reward,
            ]),
        ]);
    }
}
