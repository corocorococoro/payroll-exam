<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\MockExam;
use App\Models\Question;

class MockExamSnapshotService
{
    /** @return array<int, array<string, mixed>> */
    public function build(MockExam $exam): array
    {
        // 呼び出し側が列を絞って examQuestions を先読みしていても、
        // 採点に必要な位置・配点を欠かさないよう完全な関連を読み直す。
        $exam->load('examQuestions.question.unit', 'examQuestions.question.lesson');

        return $exam->examQuestions
            ->map(fn ($examQuestion): array => $this->item(
                $examQuestion->question,
                $examQuestion->position,
                $examQuestion->points,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $questionIds
     * @return array<int, array<string, mixed>>
     */
    public function buildFromQuestionIds(array $questionIds): array
    {
        $questions = Question::query()
            ->with('unit', 'lesson')
            ->whereKey($questionIds)
            ->get()
            ->keyBy('id');

        return collect($questionIds)->values()->map(function (int $questionId, int $index) use ($questions): array {
            $question = $questions->get($questionId);
            if (! $question instanceof Question) {
                throw new \RuntimeException("Mock snapshot question {$questionId} is missing.");
            }

            $position = $index + 1;

            return $this->item($question, $position, $position <= 35 ? 2 : 6);
        })->all();
    }

    /** @return array<string, mixed> */
    private function item(Question $question, int $position, int $points): array
    {
        return [
            'position' => $position,
            'points' => $points,
            'question_id' => $question->id,
            'content_revision' => $question->content_revision,
            'type' => $question->type->value,
            'question_text' => $question->question_text,
            'choices' => $question->choices,
            'answer' => $question->answer,
            'is_calculation' => $question->isCalculation(),
            'category' => $question->category,
            'unit_slug' => $question->unit->slug,
            'unit_name' => $question->unit->name,
            'reference_sheet_slugs' => $question->reference_sheet_slugs ?? [],
            'given_answer' => null,
            'correct' => false,
            'correct_answer' => null,
            'explanation' => $question->explanation,
            'official_sources' => app(OfficialSourceService::class)->forQuestion($question),
            'lesson_id' => $question->lesson?->id,
            'lesson_name' => $question->lesson?->name,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     * @param  array<int|string, string>  $answers
     * @return array<int, array<string, mixed>>
     */
    public function grade(array $snapshot, array $answers): array
    {
        return collect($snapshot)->map(function (array $item) use ($answers): array {
            $questionId = (int) $item['question_id'];
            $given = $answers[$questionId] ?? $answers[(string) $questionId] ?? null;
            $correct = is_string($given) && $this->checkAnswer($item, $given);

            $item['given_answer'] = $given;
            $item['correct'] = $correct;
            $item['correct_answer'] = $this->correctAnswer($item);

            return $item;
        })->values()->all();
    }

    /** @param array<string, mixed> $item */
    private function checkAnswer(array $item, string $given): bool
    {
        if (($item['type'] ?? null) === QuestionType::Choice->value) {
            return strtoupper(trim($given)) === strtoupper((string) ($item['answer']['choice'] ?? ''));
        }

        $normalized = str_replace([',', '，', '円', ' ', '　'], '', mb_convert_kana(trim($given), 'n'));

        return is_numeric($normalized)
            && abs((float) $normalized - (float) ($item['answer']['value'] ?? NAN)) < 0.001;
    }

    /** @param array<string, mixed> $item */
    private function correctAnswer(array $item): string
    {
        return ($item['type'] ?? null) === QuestionType::Choice->value
            ? (string) ($item['answer']['choice'] ?? '')
            : number_format((float) ($item['answer']['value'] ?? 0));
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     * @return array<int, array<string, mixed>>
     */
    public function playerItems(array $snapshot): array
    {
        return collect($snapshot)->map(fn (array $item): array => collect($item)->only([
            'question_id', 'position', 'points', 'type', 'question_text', 'choices',
            'is_calculation', 'reference_sheet_slugs', 'unit_name',
        ])->all() + ['id' => $item['question_id']])->values()->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $snapshot
     * @return array<int, array<string, mixed>>
     */
    public function reviewItems(array $snapshot): array
    {
        return collect($snapshot)->map(fn (array $item): array => collect($item)->only([
            'position', 'question_id', 'content_revision', 'question_text', 'unit_name',
            'given_answer', 'correct', 'correct_answer', 'explanation', 'official_sources',
            'points', 'lesson_id', 'lesson_name',
        ])->all())->values()->all();
    }
}
