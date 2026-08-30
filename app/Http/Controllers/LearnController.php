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
     * スキルツリー画面。ユニットは全開放、ユニット内レッスンは順にアンロック。
     */
    public function index(Request $request): Response
    {
        $course = Course::where('slug', 'kyuyo-2kyu')->with(['units.lessons'])->firstOrFail();

        $progresses = $request->user()->lessonProgresses()->get()->keyBy('lesson_id');
        // Base collectionのキーを問題IDにする。EloquentCollection::only() は
        // モデル自身の主キーで抽出するため、手動keyByとの組合せでは誤集計になる。
        $questionProgresses = $request->user()->questionProgresses()->get()->toBase()->keyBy('question_id');

        $units = $course->units->map(function (Unit $unit) use ($progresses, $questionProgresses): array {
            $previousCleared = true;

            return [
                'id' => $unit->id,
                'slug' => $unit->slug,
                'name' => $unit->name,
                'description' => $unit->description,
                'icon' => $unit->icon,
                'color' => $unit->color,
                'is_advanced' => $unit->is_advanced,
                'lessons' => $unit->lessons->map(function (Lesson $lesson) use ($progresses, $questionProgresses, &$previousCleared) {
                    $crown = $progresses[$lesson->id]->crown_level ?? 0;
                    $unlocked = $previousCleared;
                    $previousCleared = $crown >= 1;
                    $questionIds = $lesson->questions()->published()->pluck('id');
                    $sessionQuestionCount = min(
                        LessonRunService::QUESTION_COUNT,
                        $lesson->questions()->published()->pluck('concept_key')->unique()->count(),
                    );
                    $lessonQuestionProgresses = $questionProgresses->only($questionIds->all());
                    $seenCount = $lessonQuestionProgresses->whereNotNull('first_seen_at')->count();
                    $masteredCount = $lessonQuestionProgresses->where('state', 'mastered')->count();
                    $dueCount = $lessonQuestionProgresses
                        ->filter(fn ($progress) => $progress->due_at?->isPast() ?? false)
                        ->count();
                    $questionCount = $questionIds->count();

                    return [
                        'id' => $lesson->id,
                        'name' => $lesson->name,
                        'description' => $lesson->description,
                        'crown_level' => $crown,
                        'unlocked' => $unlocked,
                        'question_count' => $questionCount,
                        'session_question_count' => $sessionQuestionCount,
                        'seen_count' => $seenCount,
                        'mastered_count' => $masteredCount,
                        'due_count' => $dueCount,
                        'coverage_percent' => $questionCount === 0 ? 0 : (int) round($seenCount / $questionCount * 100),
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
