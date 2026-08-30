<?php

namespace App\Http\Controllers;

use App\Models\MockExam;
use App\Models\MockExamAttempt;
use App\Models\ReferenceSheet;
use App\Models\User;
use App\Services\MockExamService;
use App\Services\OfficialSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MockExamAttemptController extends Controller
{
    public function store(Request $request, MockExam $mockExam): RedirectResponse
    {
        abort_unless($mockExam->isAvailableForNewAttempt(), 404);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['standard', 'compressed'])],
        ]);

        $attempt = DB::transaction(function () use ($request, $mockExam, $validated): MockExamAttempt {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $existing = $user->mockExamAttempts()
                ->where('mock_exam_id', $mockExam->id)
                ->whereNull('finished_at')
                ->latest('started_at')
                ->first();

            if ($existing !== null && $existing->remainingSeconds() > 0) {
                return $existing;
            }

            if ($existing !== null) {
                app(MockExamService::class)->finish($existing);
            }

            return $user->mockExamAttempts()->create([
                'mock_exam_id' => $mockExam->id,
                'time_limit_minutes' => $validated['mode'] === 'compressed' ? 90 : $mockExam->time_limit_minutes,
                'started_at' => now(),
                'answers' => [],
            ]);
        });

        return to_route('mock-attempts.show', $attempt);
    }

    public function show(Request $request, MockExamAttempt $attempt, MockExamService $service): Response|RedirectResponse
    {
        $this->authorizeOwner($request, $attempt);

        if ($attempt->finished_at !== null) {
            return to_route('mock-attempts.result', $attempt);
        }

        if ($attempt->remainingSeconds() <= 0) {
            $service->finish($attempt);

            return to_route('mock-attempts.result', $attempt);
        }

        $attempt->load('mockExam.examQuestions.question.unit');
        $questions = $attempt->mockExam->examQuestions->map(fn ($examQuestion) => [
            'id' => $examQuestion->question->id,
            'position' => $examQuestion->position,
            'points' => $examQuestion->points,
            'type' => $examQuestion->question->type,
            'question_text' => $examQuestion->question->question_text,
            'choices' => $examQuestion->question->choices,
            'is_calculation' => $examQuestion->question->isCalculation(),
            'reference_sheet_slugs' => $examQuestion->question->reference_sheet_slugs ?? [],
            'unit_name' => $examQuestion->question->unit->name,
        ]);

        $sheetSlugs = $attempt->mockExam->examQuestions
            ->pluck('question.reference_sheet_slugs')->flatten()->filter()->unique();

        return Inertia::render('mock/Player', [
            'attempt' => [
                'id' => $attempt->id,
                'name' => $attempt->mockExam->name,
                'time_limit_minutes' => $attempt->time_limit_minutes,
                'remaining_seconds' => $attempt->remainingSeconds(),
                'answers' => $attempt->answers ?? [],
            ],
            'questions' => $questions,
            'reference_sheets' => ReferenceSheet::whereIn('slug', $sheetSlugs)
                ->where('fiscal_year', 2026)->orderBy('sort_order')->get(['slug', 'name', 'content']),
        ]);
    }

    public function update(Request $request, MockExamAttempt $attempt, MockExamService $service): JsonResponse
    {
        $this->authorizeOwner($request, $attempt);
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($request, $attempt, $service, $validated): JsonResponse {
            $attempt = MockExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->authorizeOwner($request, $attempt);
            abort_if($attempt->finished_at !== null, 422, 'この模試は終了しています。');

            if ($attempt->remainingSeconds() <= 0) {
                $service->finish($attempt);

                return response()->json(['message' => '制限時間を過ぎたため、自動採点しました。'], 422);
            }

            $questionIds = $attempt->mockExam->examQuestions()
                ->pluck('question_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            /** @var array<array-key, mixed> $submitted */
            $submitted = $validated['answers'];
            $answers = [];

            foreach ($questionIds as $questionId) {
                $answer = $submitted[$questionId] ?? null;

                if (is_string($answer) && trim($answer) !== '') {
                    $answers[$questionId] = $answer;
                }
            }

            $attempt->update(['answers' => $answers]);

            return response()->json(['saved' => true, 'saved_at' => now()->toIso8601String()]);
        });
    }

    public function finish(Request $request, MockExamAttempt $attempt, MockExamService $service): RedirectResponse
    {
        $this->authorizeOwner($request, $attempt);
        $service->finish($attempt);

        return to_route('mock-attempts.result', $attempt);
    }

    public function result(
        Request $request,
        MockExamAttempt $attempt,
        OfficialSourceService $officialSources,
    ): Response|RedirectResponse {
        $this->authorizeOwner($request, $attempt);

        if ($attempt->finished_at === null) {
            return to_route('mock-attempts.show', $attempt);
        }

        $attempt->load('mockExam.examQuestions.question.unit');
        $answers = $attempt->answers ?? [];
        $review = $attempt->mockExam->examQuestions->map(function ($examQuestion) use ($answers, $officialSources): array {
            $question = $examQuestion->question;
            $given = $answers[$question->id] ?? null;

            return [
                'position' => $examQuestion->position,
                'question_text' => $question->question_text,
                'unit_name' => $question->unit->name,
                'given_answer' => $given,
                'correct' => $given !== null && $question->checkAnswer($given),
                'correct_answer' => $question->type->value === 'choice'
                    ? (string) $question->answer['choice']
                    : number_format((float) $question->answer['value']),
                'explanation' => $question->explanation,
                'official_sources' => $officialSources->forQuestion($question),
                'points' => $examQuestion->points,
            ];
        });

        $weakest = collect($attempt->section_scores ?? [])->sortBy('accuracy')->keys()->take(2)->values();

        return Inertia::render('mock/Result', [
            'result' => [
                'id' => $attempt->id,
                'exam_name' => $attempt->mockExam->name,
                'score' => $attempt->score ?? 0,
                'passing_score' => $attempt->mockExam->passing_score,
                'passed' => ($attempt->score ?? 0) >= $attempt->mockExam->passing_score,
                'section_scores' => $attempt->section_scores ?? [],
                'weakest_sections' => $weakest,
                'finished_at' => $attempt->finished_at?->toIso8601String(),
            ],
            'review' => $review,
        ]);
    }

    private function authorizeOwner(Request $request, MockExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
