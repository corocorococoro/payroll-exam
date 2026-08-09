<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\MockExam;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MockExamController extends Controller
{
    public function index(Request $request): Response
    {
        $course = Course::where('slug', 'kyuyo-2kyu')->firstOrFail();
        $exams = $course->mockExams()->withCount('examQuestions')->get();
        $attempts = $request->user()->mockExamAttempts()
            ->latest('started_at')
            ->get()
            ->groupBy('mock_exam_id');

        return Inertia::render('mock/Index', [
            'exams' => $exams->map(function (MockExam $exam) use ($attempts): array {
                $history = $attempts->get($exam->id, collect());
                $active = $history->first(fn ($attempt) => $attempt->finished_at === null);
                $finished = $history->filter(fn ($attempt) => $attempt->finished_at !== null);

                return [
                    'id' => $exam->id,
                    'name' => $exam->name,
                    'description' => $exam->description,
                    'time_limit_minutes' => $exam->time_limit_minutes,
                    'passing_score' => $exam->passing_score,
                    'question_count' => $exam->exam_questions_count,
                    'active_attempt_id' => $active?->id,
                    'best_score' => $finished->max('score'),
                    'attempt_count' => $finished->count(),
                    'scores' => $finished->sortBy('finished_at')->pluck('score')->values(),
                ];
            }),
        ]);
    }
}
