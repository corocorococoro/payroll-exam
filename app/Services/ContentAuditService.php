<?php

namespace App\Services;

use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
use App\Models\MockExam;
use App\Models\Question;
use Illuminate\Support\Collection;

class ContentAuditService
{
    public function __construct(private readonly CalcVerifier $calcVerifier) {}

    /**
     * @return array{errors: list<string>, warnings: list<string>, stats: array<string, int>}
     */
    public function audit(): array
    {
        $errors = [];
        $warnings = [];
        $reviewCandidates = Question::query()
            ->where('is_active', true)
            ->where('review_status', QuestionReviewStatus::Approved->value)
            ->get();
        $questions = Question::query()->published()->get();

        $generatedActive = Question::query()
            ->where('source_id', 'like', 'gen-%')
            ->where('is_active', true)
            ->count();

        if ($generatedActive > 0) {
            $errors[] = "前置き・数値差替え型の生成問題が{$generatedActive}問公開されています。";
        }

        foreach ($reviewCandidates as $question) {
            $id = $question->source_id ?? (string) $question->id;
            $actualHash = Question::contentHash($question->only([
                'type',
                'question_text',
                'choices',
                'answer',
                'explanation',
                'common_mistake',
                'distractor_feedback',
                'calc_params',
            ]));

            if ($question->concept_key === null || trim($question->concept_key) === '') {
                $errors[] = "{$id}: concept_keyがありません。";
            }

            if ($question->learning_objective === null || trim($question->learning_objective) === '') {
                $errors[] = "{$id}: learning_objectiveがありません。";
            }

            if ($question->variant_role === null) {
                $errors[] = "{$id}: variant_roleがありません。";
            }

            if ($question->misconception_key === null || trim($question->misconception_key) === '') {
                $errors[] = "{$id}: misconception_keyがありません。";
            }

            if ($question->source_urls === null || $question->source_urls === []) {
                $errors[] = "{$id}: 一次資料URLがありません。";
            }

            if ($question->reviewed_at === null || $question->review_due_at === null) {
                $errors[] = "{$id}: レビュー日または次回レビュー期限がありません。";
            }

            if ($question->content_hash !== $actualHash) {
                $errors[] = "{$id}: 保存された内容ハッシュが実データと一致しません。";
            }

            if ($question->reviewed_content_hash !== $actualHash) {
                $errors[] = "{$id}: 現在の版がレビュー承認されていません。";
            }

            if ($question->review_due_at?->isPast()) {
                $errors[] = "{$id}: レビュー期限（{$question->review_due_at->toDateString()}）を過ぎています。";
            }

            if ($question->calc_params !== null) {
                try {
                    $computed = $this->calcVerifier->compute($question);
                    $expected = $question->answer['value'] ?? null;

                    if (! is_numeric($expected) || $computed !== (int) $expected) {
                        $errors[] = "{$id}: 計算式の再計算値{$computed}円が登録正答と一致しません。";
                    }
                } catch (\Throwable $exception) {
                    $errors[] = "{$id}: 計算式を検証できません（{$exception->getMessage()}）。";
                }
            }
        }

        $normalizedGroups = $questions
            ->groupBy(fn (Question $question): string => $this->normalize($question->question_text))
            ->filter(fn (Collection $group): bool => $group->count() > 1);

        foreach ($normalizedGroups as $group) {
            $errors[] = '同一問題文: '.$group->pluck('source_id')->implode(', ');
        }

        $this->auditLearningObjectives($questions, $errors);
        $this->appendNearDuplicateWarnings($questions, $warnings);
        $this->auditMockExams($errors);

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'stats' => [
                'published_questions' => $questions->count(),
                'learning_objectives' => $questions->pluck('concept_key')->unique()->count(),
                'variant_roles' => $questions->pluck('variant_role')->unique()->count(),
                'retired_generated_questions' => Question::query()
                    ->where('source_id', 'like', 'gen-%')
                    ->where('review_status', QuestionReviewStatus::Retired->value)
                    ->count(),
                'published_mock_exams' => MockExam::query()->where('is_published', true)->count(),
                'reviews_due' => $reviewCandidates->filter(fn (Question $question): bool => $question->review_due_at?->isPast() ?? false)->count(),
            ],
        ];
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @param  list<string>  $errors
     */
    private function auditLearningObjectives(Collection $questions, array &$errors): void
    {
        foreach ($questions->groupBy('concept_key') as $conceptKey => $variants) {
            if ($variants->count() !== 2) {
                $errors[] = "concept_key={$conceptKey}: 意味の異なる2変種で構成する必要があります（現在{$variants->count()}問）。";
            }

            $roles = $variants
                ->map(fn (Question $question): ?string => $question->variant_role?->value)
                ->filter()
                ->unique();

            if ($roles->count() !== $variants->count()) {
                $errors[] = "concept_key={$conceptKey}: 同じvariant_roleが重複しています。";
            }

            if ($variants->pluck('learning_objective')->filter()->unique()->count() !== 1) {
                $errors[] = "concept_key={$conceptKey}: learning_objectiveが変種間で一致していません。";
            }

            $hasTargetedFeedback = $variants->contains(
                fn (Question $question): bool => $question->type === QuestionType::Choice
                    && is_array($question->distractor_feedback)
                    && $question->distractor_feedback !== [],
            );

            if (! $hasTargetedFeedback) {
                $errors[] = "concept_key={$conceptKey}: 誤答選択肢別のフィードバックが1問もありません。";
            }
        }
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @param  list<string>  $warnings
     */
    private function appendNearDuplicateWarnings(Collection $questions, array &$warnings): void
    {
        $byCategory = $questions->groupBy('category');

        foreach ($byCategory as $categoryQuestions) {
            $values = $categoryQuestions->values();

            for ($left = 0; $left < $values->count(); $left++) {
                for ($right = $left + 1; $right < $values->count(); $right++) {
                    $first = $values[$left];
                    $second = $values[$right];

                    if ($first->concept_key === $second->concept_key) {
                        continue;
                    }

                    $similarity = $this->bigramSimilarity($first->question_text, $second->question_text);

                    if ($similarity >= 0.86) {
                        $warnings[] = sprintf(
                            '類似度%.0f%%: %s / %s',
                            $similarity * 100,
                            $first->source_id,
                            $second->source_id,
                        );
                    }
                }
            }
        }
    }

    /** @param list<string> $errors */
    private function auditMockExams(array &$errors): void
    {
        $exams = MockExam::query()
            ->where('is_published', true)
            ->with('examQuestions.question')
            ->get();

        foreach ($exams as $exam) {
            $items = $exam->examQuestions->values();

            if ($items->count() !== 40 || $items->sum('points') !== 100) {
                $errors[] = "{$exam->slug}: 公開模試は40問・100点である必要があります。";

                continue;
            }

            $conceptKeys = [];

            foreach ($items as $index => $item) {
                $position = $index + 1;
                $question = $item->question;
                $isKnowledge = $position <= 35;
                $expectedPoints = $isKnowledge ? 2 : 6;

                if ($item->position !== $position || $item->points !== $expectedPoints) {
                    $errors[] = "{$exam->slug}: {$position}問目の位置または配点が公式公開仕様と異なります。";
                }

                if ($question->type !== QuestionType::Choice) {
                    $errors[] = "{$exam->slug}: {$position}問目が四肢択一ではありません。";
                }

                if ($isKnowledge === $question->isCalculation()) {
                    $section = $isKnowledge ? '知識問題' : '計算問題';
                    $errors[] = "{$exam->slug}: {$position}問目が{$section}の構成条件と一致しません。";
                }

                if (! $question->is_active || $question->review_status !== QuestionReviewStatus::Approved) {
                    $errors[] = "{$exam->slug}: {$position}問目が公開承認済みではありません。";
                }

                $conceptKey = $question->concept_key ?? "question-{$question->id}";
                if (isset($conceptKeys[$conceptKey])) {
                    $errors[] = "{$exam->slug}: concept_key={$conceptKey}が模試内で重複しています。";
                }
                $conceptKeys[$conceptKey] = true;
            }
        }
    }

    private function normalize(string $text): string
    {
        $normalized = mb_strtolower(mb_convert_kana($text, 'asKV'));

        return preg_replace('/[\s　、。,.・「」『』（）()！？!?:：;；]/u', '', $normalized) ?? $normalized;
    }

    private function bigramSimilarity(string $left, string $right): float
    {
        $leftBigrams = $this->bigrams($this->normalize($left));
        $rightBigrams = $this->bigrams($this->normalize($right));
        $union = array_unique([...$leftBigrams, ...$rightBigrams]);

        if ($union === []) {
            return 0.0;
        }

        return count(array_intersect($leftBigrams, $rightBigrams)) / count($union);
    }

    /** @return list<string> */
    private function bigrams(string $text): array
    {
        $characters = mb_str_split($text);
        $bigrams = [];

        for ($index = 0; $index < count($characters) - 1; $index++) {
            $bigrams[] = $characters[$index].$characters[$index + 1];
        }

        return array_values(array_unique($bigrams));
    }
}
