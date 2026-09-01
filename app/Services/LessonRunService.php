<?php

namespace App\Services;

use App\Models\Lesson;
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
            $activeCount = $lesson->questions()
                ->practiceAvailableFor($request->user())
                ->whereIn('id', $existing['question_ids'])
                ->count();

            if ($activeCount === count($existing['question_ids'])) {
                return $existing;
            }
        }

        $bank = $lesson->questions()
            ->practiceAvailableFor($request->user())
            ->get(['id', 'study_tier']);

        $lastAttempts = $request->user()->attempts()
            ->whereIn('question_id', $bank->pluck('id'))
            ->selectRaw('question_id, MAX(created_at) AS last_attempted_at')
            ->groupBy('question_id')
            ->pluck('last_attempted_at', 'question_id');

        $progresses = $request->user()->questionProgresses()
            ->whereIn('question_id', $bank->pluck('id'))
            ->get()
            ->keyBy('question_id');

        // 合格への近さを優先する。誤答・期限到来 → 未出の合格コア → 未出の補強 →
        // 既出未定着 → 定着済みの順。各群では古いものから選び、全問へ有限回で到達する。
        $candidates = $bank
            ->sortBy(function ($question) use ($lastAttempts, $progresses): string {
                $progress = $progresses->get($question->id);
                $seen = $lastAttempts->has($question->id) || $progress?->first_seen_at !== null;
                $needsRecovery = $progress !== null && (
                    $progress->state === 'learning'
                    || ($progress->due_at?->isPast() ?? false)
                );

                $bucket = match (true) {
                    $needsRecovery => 0,
                    ! $seen && $question->study_tier === 'core' => 1,
                    ! $seen => 2,
                    $progress?->state !== 'mastered' => 3,
                    default => 4,
                };

                return sprintf(
                    '%d|%05d|%s|%010d',
                    $bucket,
                    99999 - (int) ($progress->lapses ?? 0),
                    (string) ($progress->last_seen_at ?? $lastAttempts[$question->id] ?? ''),
                    $question->id,
                );
            })
            ->values();

        /** @var list<int> $questionIds */
        $questionIds = $candidates
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

    private function key(Lesson $lesson): string
    {
        return "lesson_runs.{$lesson->id}";
    }
}
