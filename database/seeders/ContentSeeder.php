<?php

namespace Database\Seeders;

use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
use App\Enums\QuestionVariantRole;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\ReferenceSheet;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * コース構造・問題・資料集・模試を JSON データから投入する。
 * slug / source_id をキーに updateOrCreate するため、再実行でコンテンツを更新できる。
 */
class ContentSeeder extends Seeder
{
    private const int FISCAL_YEAR = 2026;

    /**
     * 正解位置をランダムに見せつつ、再シードで順番が変わらない固定シード。
     * 全問題と公開模試の正解位置が均等になることは ContentAuditService で検証する。
     */
    private const string CHOICE_ORDER_SEED = '12273';

    public function run(): void
    {
        $this->seedCourse();
        $this->seedReferenceSheets();
        $this->seedQuestions();
        $this->seedMockExams();
    }

    private function dataPath(string $file): string
    {
        return database_path("seeders/data/{$file}");
    }

    private function seedCourse(): void
    {
        $data = File::json($this->dataPath('course-2kyu.json'));

        $course = Course::updateOrCreate(
            ['slug' => $data['slug']],
            ['name' => $data['name'], 'description' => $data['description'], 'sort_order' => $data['sort_order']],
        );

        $unitSlugs = [];

        foreach ($data['units'] as $unitData) {
            $unitSlugs[] = $unitData['slug'];
            $unit = Unit::updateOrCreate(
                ['course_id' => $course->id, 'slug' => $unitData['slug']],
                [
                    'name' => $unitData['name'],
                    'description' => $unitData['description'],
                    'icon' => $unitData['icon'],
                    'color' => $unitData['color'],
                    'is_advanced' => $unitData['is_advanced'],
                    'sort_order' => $unitData['sort_order'],
                ],
            );

            $lessonSlugs = [];

            foreach ($unitData['lessons'] as $lessonData) {
                $lessonSlugs[] = $lessonData['slug'];
                Lesson::updateOrCreate(
                    ['unit_id' => $unit->id, 'slug' => $lessonData['slug']],
                    [
                        'name' => $lessonData['name'],
                        'description' => $lessonData['description'],
                        'sort_order' => $lessonData['sort_order'],
                    ],
                );
            }

            Lesson::query()
                ->where('unit_id', $unit->id)
                ->whereNotIn('slug', $lessonSlugs)
                ->delete();
        }

        Unit::query()
            ->where('course_id', $course->id)
            ->whereNotIn('slug', $unitSlugs)
            ->delete();
    }

    private function seedReferenceSheets(): void
    {
        foreach (File::json($this->dataPath('reference-sheets-2026.json')) as $sheet) {
            ReferenceSheet::updateOrCreate(
                ['slug' => $sheet['slug'], 'fiscal_year' => $sheet['fiscal_year']],
                ['name' => $sheet['name'], 'content' => $sheet['content'], 'sort_order' => $sheet['sort_order']],
            );
        }
    }

    private function seedQuestions(): void
    {
        $units = Unit::pluck('id', 'slug');
        $lessons = Lesson::with('unit')->get()->keyBy(fn (Lesson $l) => $l->unit->slug.'/'.$l->slug);
        $blueprint = $this->questionBlueprint();
        $seededSourceIds = [];

        foreach (File::files($this->dataPath('questions')) as $file) {
            foreach (File::json($file->getPathname()) as $q) {
                $this->validateQuestion($q);
                $q = $this->normalizeChoiceOrder($q);
                $pedagogy = $blueprint[$q['source_id']]
                    ?? throw new RuntimeException("Question {$q['source_id']}: question-blueprint.jsonに定義がありません。");
                $seededSourceIds[] = $q['source_id'];

                $lessonKey = $q['unit'].'/'.$q['lesson'];
                $content = [
                    'type' => $q['type'],
                    'question_text' => $q['question_text'],
                    'choices' => $q['choices'],
                    'answer' => $q['answer'],
                    'explanation' => $q['explanation'],
                    'common_mistake' => $q['common_mistake'],
                    'distractor_feedback' => $q['distractor_feedback'] ?? null,
                    'calc_params' => $q['calc_params'],
                ];
                $contentHash = Question::contentHash($content);

                Question::updateOrCreate(
                    ['source_id' => $q['source_id']],
                    [
                        'unit_id' => $units[$q['unit']] ?? throw new RuntimeException("Unknown unit {$q['unit']}"),
                        'lesson_id' => $q['lesson'] !== null
                            ? ($lessons[$lessonKey] ?? throw new RuntimeException("Unknown lesson {$lessonKey}"))->id
                            : null,
                        'concept_key' => $pedagogy['concept_key'],
                        'learning_objective' => $pedagogy['learning_objective'],
                        'variant_role' => $pedagogy['variant_role'],
                        'misconception_key' => $pedagogy['misconception_key'],
                        'type' => $content['type'],
                        'category' => $q['category'],
                        'difficulty' => $q['difficulty'],
                        'review_status' => QuestionReviewStatus::Approved,
                        'content_revision' => $q['content_revision'] ?? 1,
                        'content_hash' => $contentHash,
                        'reviewed_content_hash' => $contentHash,
                        'fiscal_year' => self::FISCAL_YEAR,
                        'question_text' => $content['question_text'],
                        'choices' => $content['choices'],
                        'answer' => $content['answer'],
                        'explanation' => $content['explanation'],
                        'common_mistake' => $content['common_mistake'],
                        'distractor_feedback' => $content['distractor_feedback'],
                        'calc_params' => $content['calc_params'],
                        'reference_sheet_slugs' => $q['reference_sheet_slugs'],
                        'source_urls' => $pedagogy['source_urls'],
                        'review_notes' => $q['review_notes'] ?? '2026年度の公表一次資料で確認。試験基準日の2026-09-01経過後に再レビューする。',
                        'reviewed_at' => $q['reviewed_at'] ?? '2026-08-09 00:00:00',
                        'review_due_at' => $q['review_due_at'] ?? '2026-09-02 00:00:00',
                        'is_active' => true,
                    ],
                );
            }
        }

        $missingBlueprintIds = array_diff(array_keys($blueprint), $seededSourceIds);
        if ($missingBlueprintIds !== []) {
            throw new RuntimeException('設計図にある問題データがありません: '.implode(', ', $missingBlueprintIds));
        }

        Question::query()
            ->whereNotNull('source_id')
            ->whereNotIn('source_id', $seededSourceIds)
            ->update([
                'review_status' => QuestionReviewStatus::Retired,
                'is_active' => false,
            ]);
    }

    /**
     * @return array<string, array{
     *     concept_key: string,
     *     learning_objective: string,
     *     variant_role: string,
     *     misconception_key: string|null,
     *     source_urls: list<string>
     * }>
     */
    private function questionBlueprint(): array
    {
        $catalog = File::json($this->dataPath('content-source-catalog.json'));
        $questions = [];

        foreach (File::json($this->dataPath('question-blueprint.json')) as $objective) {
            $sourceUrls = array_values(array_map(
                fn (string $key): string => $catalog[$key]
                    ?? throw new RuntimeException("Unknown content source key {$key}"),
                $objective['source_keys'],
            ));

            foreach ($objective['questions'] as $variant) {
                $sourceId = (string) $variant['source_id'];

                if (isset($questions[$sourceId])) {
                    throw new RuntimeException("Question {$sourceId}: 設計図で重複しています。");
                }

                $role = QuestionVariantRole::tryFrom($variant['variant_role']);
                if ($role === null) {
                    throw new RuntimeException("Question {$sourceId}: unknown variant role {$variant['variant_role']}");
                }

                $questions[$sourceId] = [
                    'concept_key' => (string) $objective['concept_key'],
                    'learning_objective' => (string) $objective['learning_objective'],
                    'variant_role' => $role->value,
                    'misconception_key' => isset($variant['misconception_key'])
                        ? (string) $variant['misconception_key']
                        : null,
                    'source_urls' => $sourceUrls,
                ];
            }
        }

        return $questions;
    }

    /**
     * コンテンツ品質のシード時バリデーション。
     *
     * @param  array<string, mixed>  $q
     */
    private function validateQuestion(array $q): void
    {
        $id = $q['source_id'] ?? '(no source_id)';

        foreach (['source_id', 'unit', 'type', 'category', 'difficulty', 'question_text', 'answer', 'explanation'] as $key) {
            if (empty($q[$key])) {
                throw new RuntimeException("Question {$id}: missing {$key}");
            }
        }

        if ($q['type'] === QuestionType::Choice->value) {
            $keys = array_column($q['choices'] ?? [], 'key');

            if ($keys !== ['A', 'B', 'C', 'D']) {
                throw new RuntimeException("Question {$id}: choice question needs choices A, B, C, D in this order");
            }

            if (! in_array($q['answer']['choice'] ?? null, $keys, true)) {
                throw new RuntimeException("Question {$id}: correct choice not found in choices");
            }

            $feedbackKeys = array_keys($q['distractor_feedback'] ?? []);
            $unknownFeedbackKeys = array_diff($feedbackKeys, $keys);
            if ($unknownFeedbackKeys !== []) {
                throw new RuntimeException("Question {$id}: distractor_feedback has unknown choice keys");
            }
        }

        if ($q['type'] === QuestionType::Numeric->value && ! is_numeric($q['answer']['value'] ?? null)) {
            throw new RuntimeException("Question {$id}: numeric question needs answer.value");
        }
    }

    /**
     * 正解位置の偏りから答えを推測できないよう、選択肢を決定的に再配置する。
     *
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function normalizeChoiceOrder(array $question): array
    {
        if ($question['type'] !== QuestionType::Choice->value) {
            return $question;
        }

        $keys = ['A', 'B', 'C', 'D'];
        $digest = hash('sha256', self::CHOICE_ORDER_SEED.':'.$question['source_id'], true);
        $targetCorrectKey = $keys[ord($digest[0]) % count($keys)];
        $originalCorrectKey = $question['answer']['choice'];
        /** @var list<array{key: string, text: string}> $choices */
        $choices = $question['choices'];
        $correctChoice = null;
        $distractors = [];

        foreach ($choices as $choice) {
            if ($choice['key'] === $originalCorrectKey) {
                $correctChoice = $choice;
            } else {
                $distractors[] = $choice;
            }
        }

        if ($correctChoice === null) {
            throw new RuntimeException("Question {$question['source_id']}: correct choice not found");
        }
        $oldToNewKeys = [];
        $reordered = [];
        $distractorIndex = 0;

        foreach ($keys as $newKey) {
            $choice = $newKey === $targetCorrectKey
                ? $correctChoice
                : $distractors[$distractorIndex++];
            $oldToNewKeys[$choice['key']] = $newKey;
            $choice['key'] = $newKey;
            $reordered[] = $choice;
        }

        $feedback = [];
        /** @var array<string, string> $sourceFeedback */
        $sourceFeedback = $question['distractor_feedback'] ?? [];
        foreach ($sourceFeedback as $oldKey => $message) {
            $feedback[$oldToNewKeys[$oldKey]] = $message;
        }
        ksort($feedback);

        $question['choices'] = $reordered;
        $question['answer']['choice'] = $targetCorrectKey;
        $question['distractor_feedback'] = $feedback;

        return $question;
    }

    private function seedMockExams(): void
    {
        $course = Course::where('slug', 'kyuyo-2kyu')->firstOrFail();
        $questionIds = Question::whereNotNull('source_id')->pluck('id', 'source_id');

        foreach (File::json($this->dataPath('mock-exams.json')) as $examData) {
            $exam = MockExam::updateOrCreate(
                ['slug' => $examData['slug']],
                [
                    'course_id' => $course->id,
                    'name' => $examData['name'],
                    'description' => $examData['description'],
                    'time_limit_minutes' => $examData['time_limit_minutes'],
                    'passing_score' => $examData['passing_score'],
                    'sort_order' => $examData['sort_order'],
                    'is_published' => true,
                ],
            );

            foreach ($examData['questions'] as $eq) {
                $questionId = $questionIds[$eq['source_id']]
                    ?? throw new RuntimeException("Mock exam {$examData['slug']}: unknown question {$eq['source_id']}");

                $exam->examQuestions()->updateOrCreate(
                    ['position' => $eq['position']],
                    ['question_id' => $questionId, 'points' => $eq['points']],
                );
            }
        }
    }
}
