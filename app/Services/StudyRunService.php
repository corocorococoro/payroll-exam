<?php

namespace App\Services;

use App\Models\LearningModule;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StudyRunService
{
    /** @return array{id: string, phase: string, question_ids: list<int>, revisions: array<int, int>, hinted_ids: list<int>, content_hash: string} */
    public function getOrStart(Request $request, LearningModule $module): array
    {
        $existing = $this->current($request, $module);
        $requestedPhase = $request->query('mode');
        if ($existing !== null && (! in_array($requestedPhase, ['guided', 'check'], true) || $requestedPhase === $existing['phase'])) {
            return $existing;
        }

        $progress = app(LearningCurriculumService::class)->progress($request->user(), $module);
        $phase = app(LearningCurriculumService::class)->phase($progress, $module);
        if (in_array($requestedPhase, ['guided', 'check'], true)) {
            $phase = $requestedPhase;
        }
        // A second check on the same day is useful practice, but never spaced evidence.
        if ($phase === 'spaced' && ! ($progress->review_due_at?->isPast() ?? false)) {
            $phase = 'check';
        }

        $bank = $module->questions()->published()->practiceAvailableFor($request->user())->get();
        $attempts = $request->user()->attempts()->whereIn('question_id', $bank->pluck('id'))
            ->orderByDesc('id')->get()->unique('question_id')->keyBy('question_id');
        $candidates = $bank->sortBy(function (Question $question) use ($attempts, $phase, $module): string {
            $attempt = $attempts->get($question->id);
            $role = match ($question->variant_role?->value) {
                'recall' => 0, 'workflow' => 1, 'application' => 2, 'calculation' => 3,
                'misconception' => 4, default => 5,
            };

            // New variants first for self-checks; guided runs start with the basic rule.
            return $phase === 'guided'
                ? sprintf('%d|%d|%d|%010d', $question->id === $module->example_question_id ? 1 : 0,
                    $role, $question->study_tier === 'core' ? 0 : 1, $question->id)
                : sprintf('%d|%d|%s|%d|%010d', $attempt === null ? 0 : 1,
                    $question->study_tier === 'core' ? 0 : 1, (string) ($attempt->created_at ?? ''), $role, $question->id);
        })->values();

        // Cover different concepts before taking a second variant of the same concept.
        $limit = $phase === 'guided' ? min(3, max(1, $bank->count() - 2)) : min(5, $bank->count());
        $selected = $candidates->unique('concept_key')->take($limit);
        $selected = $selected->concat($candidates->whereNotIn('id', $selected->pluck('id'))
            ->take($limit - $selected->count()));
        $questionIds = [];
        $revisions = [];
        foreach ($selected as $question) {
            $questionIds[] = $question->id;
            $revisions[$question->id] = $question->content_revision;
        }
        $run = [
            'id' => (string) Str::uuid(), 'phase' => $phase,
            'question_ids' => $questionIds,
            'revisions' => $revisions,
            'hinted_ids' => [], 'content_hash' => $module->content_hash,
        ];
        $request->session()->put($this->key($module), $run);

        return $run;
    }

    /** @return array{id: string, phase: string, question_ids: list<int>, revisions: array<int, int>, hinted_ids: list<int>, content_hash: string}|null */
    public function current(Request $request, LearningModule $module): ?array
    {
        $run = $request->session()->get($this->key($module));
        if (! is_array($run) || ! isset($run['id'], $run['phase'], $run['question_ids'], $run['revisions'], $run['hinted_ids'], $run['content_hash'])
            || ! $module->is_active || $run['content_hash'] !== $module->content_hash) {
            return null;
        }
        $questions = $module->questions()->published()->practiceAvailableFor($request->user())
            ->whereIn('questions.id', $run['question_ids'])->get();
        if ($questions->count() !== count($run['question_ids']) || $questions->contains(
            fn (Question $question): bool => ($run['revisions'][$question->id] ?? null) !== $question->content_revision,
        )) {
            return null;
        }

        return $run;
    }

    public function markHint(Request $request, LearningModule $module, int $questionId): void
    {
        $run = $this->current($request, $module);
        abort_if($run === null || ! in_array($questionId, $run['question_ids'], true), 422, '学習一覧から開き直してください。');
        // Showing the example also assists any later question identical to that example.
        $run['hinted_ids'] = array_values(array_unique([...$run['hinted_ids'], $questionId, $module->example_question_id]));
        $request->session()->put($this->key($module), $run);
    }

    public function clear(Request $request, LearningModule $module): void
    {
        $request->session()->forget($this->key($module));
    }

    private function key(LearningModule $module): string
    {
        return "study_runs.{$module->id}";
    }
}
