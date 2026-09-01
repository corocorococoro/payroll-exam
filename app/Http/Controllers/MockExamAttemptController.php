<?php

namespace App\Http\Controllers;

use App\Models\MockExam;
use App\Models\MockExamAttempt;
use App\Models\ReferenceSheet;
use App\Models\User;
use App\Services\MockExamService;
use App\Services\MockExamSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MockExamAttemptController extends Controller
{
    public function store(Request $request, MockExam $mockExam, MockExamSnapshotService $snapshots): RedirectResponse
    {
        abort_unless($mockExam->isAvailableForNewAttempt(), 404);

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['standard', 'compressed'])],
        ]);

        $attempt = DB::transaction(function () use ($request, $mockExam, $snapshots, $validated): MockExamAttempt {
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
                'review_snapshot' => $snapshots->build($mockExam),
            ]);
        });

        return to_route('mock-attempts.show', $attempt);
    }

    public function show(
        Request $request,
        MockExamAttempt $attempt,
        MockExamService $service,
        MockExamSnapshotService $snapshots,
    ): Response|RedirectResponse {
        $this->authorizeOwner($request, $attempt);

        if ($attempt->finished_at !== null) {
            return to_route('mock-attempts.result', $attempt);
        }

        if ($attempt->remainingSeconds() <= 0) {
            $service->finish($attempt);

            return to_route('mock-attempts.result', $attempt);
        }

        $snapshot = $attempt->review_snapshot ?? $snapshots->build($attempt->mockExam);
        $questions = $snapshots->playerItems($snapshot);
        $sheetSlugs = collect($snapshot)->pluck('reference_sheet_slugs')->flatten()->filter()->unique();

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

    public function update(
        Request $request,
        MockExamAttempt $attempt,
        MockExamService $service,
        MockExamSnapshotService $snapshots,
    ): JsonResponse {
        $this->authorizeOwner($request, $attempt);
        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($request, $attempt, $service, $snapshots, $validated): JsonResponse {
            $attempt = MockExamAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->authorizeOwner($request, $attempt);
            abort_if($attempt->finished_at !== null, 422, 'この模試は終了しています。');

            if ($attempt->remainingSeconds() <= 0) {
                $service->finish($attempt);

                return response()->json(['message' => '制限時間を過ぎたため、自動採点しました。'], 422);
            }

            $snapshot = $attempt->review_snapshot ?? $snapshots->build($attempt->mockExam);
            $questionIds = collect($snapshot)
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
        MockExamSnapshotService $snapshots,
    ): Response|RedirectResponse {
        $this->authorizeOwner($request, $attempt);

        if ($attempt->finished_at === null) {
            return to_route('mock-attempts.show', $attempt);
        }

        $snapshot = $attempt->review_snapshot ?? $snapshots->grade(
            $snapshots->build($attempt->mockExam),
            $attempt->answers ?? [],
        );
        $review = collect($snapshots->reviewItems($snapshot));

        $weakest = collect($attempt->unit_scores ?? [])
            ->sortBy('accuracy')
            ->filter(fn (array $score): bool => ($score['accuracy'] ?? 0) < 70)
            ->take(2)
            ->pluck('name')
            ->values();
        $remediation = $review
            ->filter(fn (array $item): bool => ! $item['correct'] && $item['lesson_id'] !== null)
            ->groupBy('lesson_id')
            ->map(function ($items): array {
                $first = $items->first();

                return [
                    'lesson_id' => $first['lesson_id'],
                    'lesson_name' => $first['lesson_name'],
                    'unit_name' => $first['unit_name'],
                    'missed_count' => $items->count(),
                    'missed_points' => $items->sum('points'),
                    'href' => "/lessons/{$first['lesson_id']}",
                ];
            })
            ->sortByDesc('missed_points')
            ->take(2)
            ->values();

        return Inertia::render('mock/Result', [
            'result' => [
                'id' => $attempt->id,
                'exam_name' => $attempt->mockExam->name,
                'score' => $attempt->score ?? 0,
                'passing_score' => $attempt->mockExam->passing_score,
                'passed' => ($attempt->score ?? 0) >= $attempt->mockExam->passing_score,
                'section_scores' => $attempt->section_scores ?? [],
                'unit_scores' => $attempt->unit_scores ?? [],
                'knowledge_score' => $attempt->knowledge_score ?? 0,
                'calculation_score' => $attempt->calculation_score ?? 0,
                'weakest_sections' => $weakest,
                'finished_at' => $attempt->finished_at->toIso8601String(),
            ],
            'remediation' => $remediation,
            'review' => $review,
        ]);
    }

    private function authorizeOwner(Request $request, MockExamAttempt $attempt): void
    {
        abort_unless($attempt->user_id === $request->user()->id, 403);
    }
}
