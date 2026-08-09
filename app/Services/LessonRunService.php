<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * A lesson is a short, server-issued run rather than the lesson's entire question bank.
 * The run stored in the session is also the allow-list used by AnswerController.
 */
class LessonRunService
{
    public const int QUESTION_COUNT = 7;

    /** @return array{question_ids: list<int>, started_at: string} */
    public function getOrStart(Request $request, Lesson $lesson): array
    {
        $existing = $this->current($request, $lesson);

        if ($existing !== null) {
            $activeCount = $lesson->questions()->whereIn('id', $existing['question_ids'])->count();

            if ($activeCount === count($existing['question_ids'])) {
                return $existing;
            }
        }

        $candidates = $lesson->questions()
            ->get(['id', 'concept_key'])
            ->shuffle()
            ->unique(fn ($question): string => $question->concept_key ?? "question-{$question->id}")
            ->values();

        $lastAttempts = $request->user()->attempts()
            ->whereIn('question_id', $candidates->pluck('id'))
            ->selectRaw('question_id, MAX(created_at) AS last_attempted_at')
            ->groupBy('question_id')
            ->pluck('last_attempted_at', 'question_id');

        $unseen = $candidates
            ->reject(fn ($question): bool => $lastAttempts->has($question->id))
            ->shuffle();
        $seen = $candidates
            ->filter(fn ($question): bool => $lastAttempts->has($question->id))
            ->sortBy(fn ($question): string => (string) $lastAttempts[$question->id]);

        /** @var list<int> $questionIds */
        $questionIds = $unseen
            ->concat($seen)
            ->take(self::QUESTION_COUNT)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $run = [
            'question_ids' => $questionIds,
            'started_at' => now()->toIso8601String(),
        ];

        $request->session()->put($this->key($lesson), $run);

        return $run;
    }

    /** @return array{question_ids: list<int>, started_at: string}|null */
    public function current(Request $request, Lesson $lesson): ?array
    {
        $run = $request->session()->get($this->key($lesson));

        if (! is_array($run) || ! isset($run['question_ids'], $run['started_at']) || ! is_array($run['question_ids'])) {
            return null;
        }

        try {
            CarbonImmutable::parse((string) $run['started_at']);
        } catch (\Throwable) {
            return null;
        }

        return [
            'question_ids' => array_values(array_map('intval', $run['question_ids'])),
            'started_at' => (string) $run['started_at'],
        ];
    }

    public function clear(Request $request, Lesson $lesson): void
    {
        $request->session()->forget($this->key($lesson));
    }

    public function isUnlocked(User $user, Lesson $lesson): bool
    {
        $previous = Lesson::query()
            ->where('unit_id', $lesson->unit_id)
            ->where('sort_order', '<', $lesson->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if ($previous === null) {
            return true;
        }

        return $user->lessonProgresses()
            ->where('lesson_id', $previous->id)
            ->where('crown_level', '>=', 1)
            ->exists();
    }

    private function key(Lesson $lesson): string
    {
        return "lesson_runs.{$lesson->id}";
    }
}
