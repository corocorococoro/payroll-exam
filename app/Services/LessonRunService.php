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
    public const int QUESTION_COUNT = 10;

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

        $bank = $lesson->questions()->get(['id', 'concept_key', 'variant_role']);

        $lastAttempts = $request->user()->attempts()
            ->whereIn('question_id', $bank->pluck('id'))
            ->selectRaw('question_id, MAX(created_at) AS last_attempted_at')
            ->groupBy('question_id')
            ->pluck('last_attempted_at', 'question_id');

        // 同じ学習目標は1回に1問だけ。未着手の概念、既出変種が少ない概念、
        // 最終出題が古い概念の順で決定し、ランダムな取りこぼしをなくす。
        // 概念内でも未出変種をID順に出し、全問へ有限回で到達できるようにする。
        $candidates = $bank
            ->groupBy(fn ($question): string => $question->concept_key ?? "question-{$question->id}")
            ->map(function ($variants) use ($lastAttempts) {
                $unseen = $variants
                    ->reject(fn ($question): bool => $lastAttempts->has($question->id))
                    ->sortBy('id');

                if ($unseen->isNotEmpty()) {
                    $question = $unseen->first();
                } else {
                    $question = $variants
                        ->sortBy(fn ($variant): string => (string) $lastAttempts[$variant->id])
                        ->first();
                }

                $attemptedAt = $variants
                    ->map(fn ($variant) => $lastAttempts[$variant->id] ?? null)
                    ->filter()
                    ->sortDesc()
                    ->first();

                return [
                    'question' => $question,
                    'seen_variant_count' => $variants->filter(
                        fn ($variant): bool => $lastAttempts->has($variant->id),
                    )->count(),
                    'last_attempted_at' => $attemptedAt,
                ];
            })
            ->filter(fn (array $candidate): bool => $candidate['question'] !== null)
            ->sortBy(fn (array $candidate): string => sprintf(
                '%03d|%s|%010d',
                $candidate['seen_variant_count'],
                (string) ($candidate['last_attempted_at'] ?? ''),
                $candidate['question']->id,
            ))
            ->values();

        /** @var list<int> $questionIds */
        $questionIds = $candidates
            ->take(self::QUESTION_COUNT)
            ->pluck('question.id')
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
