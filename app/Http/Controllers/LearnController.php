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

        $units = $course->units->map(function (Unit $unit) use ($progresses): array {
            $previousCleared = true;

            return [
                'id' => $unit->id,
                'slug' => $unit->slug,
                'name' => $unit->name,
                'description' => $unit->description,
                'icon' => $unit->icon,
                'color' => $unit->color,
                'is_advanced' => $unit->is_advanced,
                'lessons' => $unit->lessons->map(function (Lesson $lesson) use ($progresses, &$previousCleared) {
                    $crown = $progresses[$lesson->id]->crown_level ?? 0;
                    $unlocked = $previousCleared;
                    $previousCleared = $crown >= 1;

                    return [
                        'id' => $lesson->id,
                        'name' => $lesson->name,
                        'description' => $lesson->description,
                        'crown_level' => $crown,
                        'unlocked' => $unlocked,
                        'question_count' => min(LessonRunService::QUESTION_COUNT, $lesson->questions()->count()),
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
