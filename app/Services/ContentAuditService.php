<?php

namespace App\Services;

use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
use App\Models\Lesson;
use App\Models\MockExam;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ContentAuditService
{
    private const int EXPECTED_PUBLISHED_MOCK_EXAMS = 3;

    /** @var list<string> */
    private const array EXPECTED_UNIT_SLUGS = ['shikyu', 'roudou', 'shaho', 'zei', 'keisan'];

    public function __construct(
        private readonly CalcVerifier $calcVerifier,
        private readonly OfficialSourceService $officialSources,
    ) {}

    /**
     * @return array{errors: list<string>, warnings: list<string>, stats: array<string, int>}
     */
    public function audit(): array
    {
        $errors = [];
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

            if ($question->source_urls === null || $question->source_urls === []) {
                $errors[] = "{$id}: 参照元がありません。";
            } elseif (! collect($question->source_urls)->every(
                fn (string $url): bool => $this->officialSources->isOfficialUrl($url),
            )) {
                $errors[] = "{$id}: 公式一次資料以外のURLが含まれています。";
            }

            if ($question->verification_status !== 'official_sources_reviewed') {
                $errors[] = "{$id}: 公式一次資料との照合状態が記録されていません。";
            }

            if ($question->scope_status !== 'exam_2026-09-01') {
                $errors[] = "{$id}: 2026年2級試験の法令基準日が記録されていません。";
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
        $this->auditAnswerPositionBalance($questions, $errors);
        $this->auditMockExams($errors);
        $this->auditPracticeBankCoverage($errors);

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => [],
            'stats' => [
                'published_questions' => $questions->count(),
                'learning_objectives' => $questions->pluck('concept_key')->unique()->count(),
                'core_questions' => $questions->where('study_tier', 'core')->count(),
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
    private function auditAnswerPositionBalance(Collection $questions, array &$errors): void
    {
        $counts = $this->answerPositionCounts($questions);

        if ($counts->max() - $counts->min() > 10) {
            $errors[] = '公開問題の正解位置が偏っています（'.$this->formatAnswerPositionCounts($counts).'）。';
        }
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @param  list<string>  $errors
     */
    private function auditLearningObjectives(Collection $questions, array &$errors): void
    {
        foreach ($questions->groupBy('concept_key') as $conceptKey => $variants) {
            if ($variants->pluck('learning_objective')->filter()->unique()->count() !== 1) {
                $errors[] = "concept_key={$conceptKey}: learning_objectiveが変種間で一致していません。";
            }

            if ($variants->where('study_tier', 'core')->isEmpty()) {
                $errors[] = "concept_key={$conceptKey}: 合格コア問題がありません。";
            }
        }
    }

    /** @param list<string> $errors */
    private function auditMockExams(array &$errors): void
    {
        $exams = MockExam::query()
            ->where('is_published', true)
            ->with('examQuestions.question.unit')
            ->get();

        if ($exams->count() !== self::EXPECTED_PUBLISHED_MOCK_EXAMS) {
            $errors[] = '公開模試は'.self::EXPECTED_PUBLISHED_MOCK_EXAMS.'回必要です（現在'.$exams->count().'回）。';
        }

        /** @var array<int, list<string>> $questionExams */
        $questionExams = [];

        foreach ($exams as $exam) {
            $items = $exam->examQuestions->values();

            if ($items->count() !== 40 || $items->sum('points') !== 100) {
                $errors[] = "{$exam->slug}: 公開模試は40問・100点である必要があります。";

                continue;
            }

            $unitSlugs = collect();
            $questionIds = collect();
            $conceptKeys = collect();

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
                } elseif (count($question->choices ?? []) !== 4) {
                    $errors[] = "{$exam->slug}: {$position}問目の選択肢が4つではありません。";
                }

                if ($isKnowledge === $question->isCalculation()) {
                    $section = $isKnowledge ? '知識問題' : '計算問題';
                    $errors[] = "{$exam->slug}: {$position}問目が{$section}の構成条件と一致しません。";
                }

                if (! $question->is_active || $question->review_status !== QuestionReviewStatus::Approved) {
                    $errors[] = "{$exam->slug}: {$position}問目が公開承認済みではありません。";
                }

                $unitSlugs->push($question->unit->slug);
                $questionIds->push($question->id);
                $conceptKeys->push($question->concept_key);
                $questionExams[$question->id] ??= [];
                $questionExams[$question->id][] = $exam->slug;
            }

            $duplicateQuestionIds = $questionIds->countBy()->filter(fn (int $count): bool => $count > 1)->keys();
            if ($duplicateQuestionIds->isNotEmpty()) {
                $sourceIds = Question::query()
                    ->whereKey($duplicateQuestionIds)
                    ->pluck('source_id')
                    ->filter()
                    ->implode('、');
                $errors[] = "{$exam->slug}: 同じ問題が模試内で重複しています（{$sourceIds}）。";
            }

            $duplicateConcepts = $conceptKeys->filter()->countBy()->filter(fn (int $count): bool => $count > 1);
            if ($duplicateConcepts->isNotEmpty()) {
                $detail = $duplicateConcepts
                    ->map(fn (int $count, string $concept): string => "{$concept}={$count}")
                    ->implode('、');
                $errors[] = "{$exam->slug}: 同じ論点が模試内で重複しています（{$detail}）。";
            }

            $unitCounts = $unitSlugs->countBy();
            $missingUnits = collect(self::EXPECTED_UNIT_SLUGS)->diff($unitCounts->keys());
            if ($missingUnits->isNotEmpty()) {
                $errors[] = "{$exam->slug}: 模試に未出の単元があります（{$missingUnits->implode('、')}）。";
            }

            $imbalancedUnits = $unitCounts->filter(
                fn (int $count): bool => $count < 3 || $count > 14,
            );
            if ($imbalancedUnits->isNotEmpty()) {
                $detail = $imbalancedUnits
                    ->map(fn (int $count, string $slug): string => "{$slug}={$count}")
                    ->implode('、');
                $errors[] = "{$exam->slug}: 単元別出題数が許容範囲（3〜14問）外です（{$detail}）。";
            }

            $answerCounts = $this->answerPositionCounts($items->pluck('question'));
            if ($answerCounts->max() - $answerCounts->min() > 2) {
                $errors[] = "{$exam->slug}: 正解位置が偏っています（{$this->formatAnswerPositionCounts($answerCounts)}）。";
            }
        }

        foreach ($questionExams as $questionId => $examSlugs) {
            $examSlugs = array_values(array_unique($examSlugs));

            if (count($examSlugs) > 1) {
                $sourceId = Question::query()->whereKey($questionId)->value('source_id') ?? (string) $questionId;
                $errors[] = "{$sourceId}: 公開模試間で問題が重複しています（".implode('、', $examSlugs).'）。';
            }
        }
    }

    /** @param list<string> $errors */
    private function auditPracticeBankCoverage(array &$errors): void
    {
        $unavailableLessons = Lesson::query()
            ->whereHas('questions', fn (Builder $questions): Builder => $this->publishableQuestions($questions))
            ->whereDoesntHave('questions', fn (Builder $questions): Builder => $this->publishableQuestions($questions)
                ->whereDoesntHave(
                    'mockExamQuestions.mockExam',
                    fn (Builder $mockExam): Builder => $mockExam->where('is_published', true),
                ))
            ->pluck('name');

        foreach ($unavailableLessons as $lessonName) {
            $errors[] = "{$lessonName}: 通常学習に出題できる問題がありません。";
        }
    }

    /**
     * @param  Builder<Model>  $questions
     * @return Builder<Model>
     */
    private function publishableQuestions(Builder $questions): Builder
    {
        return $questions
            ->where('is_active', true)
            ->where('review_status', QuestionReviewStatus::Approved->value)
            ->where('reviewed_content_hash', '!=', '')
            ->whereColumn('reviewed_content_hash', 'content_hash')
            ->whereNotNull('reviewed_at')
            ->whereNotNull('review_due_at')
            ->where('review_due_at', '>=', now());
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @return Collection<string, int>
     */
    private function answerPositionCounts(Collection $questions): Collection
    {
        $counts = collect(['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0]);

        foreach ($questions->where('type', QuestionType::Choice) as $question) {
            $key = $question->answer['choice'] ?? null;
            if (is_string($key) && $counts->has($key)) {
                $counts->put($key, $counts->get($key) + 1);
            }
        }

        return $counts;
    }

    /** @param Collection<string, int> $counts */
    private function formatAnswerPositionCounts(Collection $counts): string
    {
        return $counts->map(fn (int $count, string $key): string => "{$key}={$count}")->implode('、');
    }

    private function normalize(string $text): string
    {
        $normalized = mb_strtolower(mb_convert_kana($text, 'asKV'));

        return preg_replace('/[\s　、。,.・「」『』（）()！？!?:：;；]/u', '', $normalized) ?? $normalized;
    }
}
