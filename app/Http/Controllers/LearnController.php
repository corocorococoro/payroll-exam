<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Unit;
use App\Services\LessonRunService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnController extends Controller
{
    /**
     * 合格ルート順に表示するが、模試の弱点から直接戻れるよう全レッスンを開放する。
     */
    public function index(Request $request): Response
    {
        $course = Course::where('slug', 'kyuyo-2kyu')->with(['units.lessons'])->firstOrFail();

        $progresses = $request->user()->lessonProgresses()->get()->keyBy('lesson_id');
        // Base collectionのキーを問題IDにする。EloquentCollection::only() は
        // モデル自身の主キーで抽出するため、手動keyByとの組合せでは誤集計になる。
        $questionProgresses = $request->user()->questionProgresses()->get()->toBase()->keyBy('question_id');

        $units = $course->units->map(function (Unit $unit) use ($progresses, $questionProgresses): array {
            return [
                'id' => $unit->id,
                'slug' => $unit->slug,
                'name' => $unit->name,
                'description' => $unit->description,
                'icon' => $unit->icon,
                'color' => $unit->color,
                'is_advanced' => $unit->is_advanced,
                'lessons' => $unit->lessons->map(function (Lesson $lesson) use ($progresses, $questionProgresses) {
                    $crown = $progresses[$lesson->id]->crown_level ?? 0;
                    $lessonQuestions = $lesson->questions()->published()->get(['id', 'study_tier']);
                    $questionIds = $lessonQuestions->pluck('id');
                    $sessionQuestionCount = min(
                        LessonRunService::QUESTION_COUNT,
                        $questionIds->count(),
                    );
                    $lessonQuestionProgresses = $questionProgresses->only($questionIds->all());
                    $seenCount = $lessonQuestionProgresses->whereNotNull('first_seen_at')->count();
                    $masteredCount = $lessonQuestionProgresses->where('state', 'mastered')->count();
                    $dueCount = $lessonQuestionProgresses
                        ->filter(fn ($progress) => $progress->due_at?->isPast() ?? false)
                        ->count();
                    $questionCount = $questionIds->count();
                    $coreQuestionIds = $lessonQuestions->where('study_tier', 'core')->pluck('id');
                    $coreQuestionProgresses = $questionProgresses->only($coreQuestionIds->all());
                    $coreQuestionCount = $coreQuestionIds->count();
                    $coreSeenCount = $coreQuestionProgresses->whereNotNull('first_seen_at')->count();
                    $coreMasteredCount = $coreQuestionProgresses->where('state', 'mastered')->count();

                    return [
                        'id' => $lesson->id,
                        'name' => $lesson->name,
                        'description' => $lesson->description,
                        'crown_level' => $crown,
                        'question_count' => $questionCount,
                        'session_question_count' => $sessionQuestionCount,
                        'seen_count' => $seenCount,
                        'mastered_count' => $masteredCount,
                        'due_count' => $dueCount,
                        'coverage_percent' => $questionCount === 0 ? 0 : (int) round($seenCount / $questionCount * 100),
                        'core_question_count' => $coreQuestionCount,
                        'core_seen_count' => $coreSeenCount,
                        'core_mastered_count' => $coreMasteredCount,
                        'core_coverage_percent' => $coreQuestionCount === 0 ? 0 : (int) round($coreSeenCount / $coreQuestionCount * 100),
                    ];
                })->values()->all(),
            ];
        })->values();

        return Inertia::render('learn/Index', [
            'course' => ['name' => $course->name],
            'units' => $units,
        ]);
    }
}
