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
                        'study_guide' => $this->validateStudyGuide($lessonData),
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

    /**
     * @param  array<string, mixed>  $lessonData
     * @return array{why: string, goal: string, key_points: list<string>, common_traps: list<string>}
     */
    private function validateStudyGuide(array $lessonData): array
    {
        $slug = (string) ($lessonData['slug'] ?? '(no slug)');
        $guide = $lessonData['study_guide'] ?? null;

        if (! is_array($guide)) {
            throw new RuntimeException("Lesson {$slug}: study_guide is missing");
        }

        $why = $guide['why'] ?? null;
        $goal = $guide['goal'] ?? null;
        $keyPoints = $guide['key_points'] ?? null;
        $commonTraps = $guide['common_traps'] ?? null;

        if (! is_string($why) || trim($why) === '' || ! is_string($goal) || trim($goal) === '') {
            throw new RuntimeException("Lesson {$slug}: why and goal are required");
        }

        if (! is_array($keyPoints) || ! array_is_list($keyPoints) || count($keyPoints) !== 3 || array_filter(
            $keyPoints,
            fn ($point): bool => ! is_string($point) || trim($point) === '',
        ) !== []) {
            throw new RuntimeException("Lesson {$slug}: exactly three key_points are required");
        }

        if (! is_array($commonTraps) || ! array_is_list($commonTraps) || count($commonTraps) !== 2 || array_filter(
            $commonTraps,
            fn ($trap): bool => ! is_string($trap) || trim($trap) === '',
        ) !== []) {
            throw new RuntimeException("Lesson {$slug}: exactly two common_traps are required");
        }

        /** @var list<string> $keyPoints */
        /** @var list<string> $commonTraps */
        return [
            'why' => $why,
            'goal' => $goal,
            'key_points' => $keyPoints,
            'common_traps' => $commonTraps,
        ];
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
        $bank = File::json($this->dataPath('question-bank.json'));
        $sourceCatalog = File::json($this->dataPath('official-sources.json'));
        $topics = $bank['topics'] ?? [];
        $release = $bank['release'] ?? [];
        /** @var list<array<string, mixed>> $questions */
        $questions = $bank['questions'] ?? [];
        $choiceTargets = $this->choiceTargets($questions);
        $seededSourceIds = [];

        if (($release['question_count'] ?? null) !== count($questions)) {
            throw new RuntimeException('問題バンクのquestion_countと実際の問題数が一致しません。');
        }

        $coreQuestionCount = collect($questions)->where('study_tier', 'core')->count();
        if (($release['core_question_count'] ?? null) !== $coreQuestionCount) {
            throw new RuntimeException('問題バンクのcore_question_countと実際の合格コア問題数が一致しません。');
        }

        foreach ($questions as $q) {
            $this->validateQuestion($q, $topics, $sourceCatalog);
            $q = $this->normalizeChoiceOrder($q, $choiceTargets[$q['id']] ?? null);
            $seededSourceIds[] = $q['id'];
            $sourceUrls = array_values(array_map(
                fn (string $key): string => $sourceCatalog[$key]['url'],
                $q['source_keys'],
            ));

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
                ['source_id' => $q['id']],
                [
                    'unit_id' => $units[$q['unit']] ?? throw new RuntimeException("Unknown unit {$q['unit']}"),
                    'lesson_id' => $q['lesson'] !== null
                        ? ($lessons[$lessonKey] ?? throw new RuntimeException("Unknown lesson {$lessonKey}"))->id
                        : null,
                    'concept_key' => $q['topic_key'],
                    'learning_objective' => $topics[$q['topic_key']],
                    'variant_role' => $q['role'],
                    'misconception_key' => $q['misconception_key'] ?? null,
                    'type' => $content['type'],
                    'category' => $q['category'],
                    'difficulty' => $q['difficulty'],
                    'review_status' => QuestionReviewStatus::Approved,
                    'verification_status' => 'official_sources_reviewed',
                    'scope_status' => 'exam_'.($release['legal_as_of'] ?? throw new RuntimeException('法令基準日がありません。')),
                    'exam_role' => $q['exam_role'] ?? ($content['calc_params'] === null ? 'knowledge' : 'calculation'),
                    'study_tier' => $q['study_tier'],
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
                    'source_urls' => $sourceUrls,
                    'review_notes' => $q['review_notes'] ?? '2026年9月1日の試験基準に対し、公式試験案内・法令一次資料・計算結果を確認。',
                    'reviewed_at' => $q['reviewed_at'] ?? $release['reviewed_at'].' 00:00:00',
                    'review_due_at' => $q['review_due_at'] ?? $release['review_due_at'].' 00:00:00',
                    'is_active' => true,
                ],
            );
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
     * コンテンツ品質のシード時バリデーション。
     *
     * @param  array<string, mixed>  $q
     * @param  array<string, string>  $topics
     * @param  array<string, array{label: string, url: string}>  $sourceCatalog
     */
    private function validateQuestion(array $q, array $topics, array $sourceCatalog): void
    {
        $id = $q['id'] ?? '(no id)';

        foreach (['id', 'topic_key', 'role', 'study_tier', 'source_keys', 'unit', 'type', 'category', 'difficulty', 'question_text', 'answer', 'explanation'] as $key) {
            if (empty($q[$key])) {
                throw new RuntimeException("Question {$id}: missing {$key}");
            }
        }

        if (! isset($topics[$q['topic_key']])) {
            throw new RuntimeException("Question {$id}: unknown topic {$q['topic_key']}");
        }

        if (QuestionVariantRole::tryFrom((string) $q['role']) === null) {
            throw new RuntimeException("Question {$id}: unknown role {$q['role']}");
        }

        if (! in_array($q['study_tier'], ['core', 'reinforcement'], true)) {
            throw new RuntimeException("Question {$id}: unknown study tier {$q['study_tier']}");
        }

        foreach ($q['source_keys'] as $sourceKey) {
            if (! isset($sourceCatalog[$sourceKey]['url'], $sourceCatalog[$sourceKey]['label'])) {
                throw new RuntimeException("Question {$id}: unknown official source {$sourceKey}");
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
    private function normalizeChoiceOrder(array $question, ?string $targetCorrectKey): array
    {
        if ($question['type'] !== QuestionType::Choice->value) {
            return $question;
        }

        $keys = ['A', 'B', 'C', 'D'];
        if (! in_array($targetCorrectKey, $keys, true)) {
            throw new RuntimeException("Question {$question['id']}: correct-choice target is missing");
        }
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
            throw new RuntimeException("Question {$question['id']}: correct choice not found");
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

    /**
     * 公開模試は各正解位置を10問ずつにし、残りを現在の最少位置へ配って
     * 問題バンク全体も均等にする。正本IDと固定シードから再現可能に決定する。
     *
     * @param  list<array<string, mixed>>  $questions
     * @return array<string, string>
     */
    private function choiceTargets(array $questions): array
    {
        $keys = ['A', 'B', 'C', 'D'];
        $targets = [];
        $counts = array_fill_keys($keys, 0);

        foreach (File::json($this->dataPath('mock-exams.json')) as $exam) {
            foreach ($exam['questions'] as $item) {
                $questionId = (string) $item['question_id'];
                $target = match (((int) $item['position'] - 1) % count($keys)) {
                    0 => 'A',
                    1 => 'B',
                    2 => 'C',
                    3 => 'D',
                    default => throw new RuntimeException("Mock exam position must be positive: {$item['position']}"),
                };

                if (isset($targets[$questionId]) && $targets[$questionId] !== $target) {
                    throw new RuntimeException("Question {$questionId}: 模試間で正解位置が競合しています。");
                }

                if (! isset($targets[$questionId])) {
                    $targets[$questionId] = $target;
                    $counts[$target]++;
                }
            }
        }

        foreach ($questions as $question) {
            $questionId = (string) $question['id'];
            if ($question['type'] !== QuestionType::Choice->value || isset($targets[$questionId])) {
                continue;
            }

            $minimum = min($counts);
            $leastUsed = array_values(array_filter(
                $keys,
                fn (string $key): bool => $counts[$key] === $minimum,
            ));
            $target = $leastUsed[0] ?? throw new RuntimeException('正解位置の割当てに失敗しました。');
            $targets[$questionId] = $target;
            $counts[$target]++;
        }

        return $targets;
    }

    private function seedMockExams(): void
    {
        $course = Course::where('slug', 'kyuyo-2kyu')->firstOrFail();
        $questionIds = Question::whereNotNull('source_id')->pluck('id', 'source_id');
        /** @var list<array{slug: string, name: string, description: string, time_limit_minutes: int, passing_score: int, sort_order: int, questions: list<array{position: int, question_id: string, points: int}>}> $mockExams */
        $mockExams = File::json($this->dataPath('mock-exams.json'));
        $availableMockExams = collect($mockExams)->map(function (array $examData) use ($questionIds): array {
            $questionKeys = collect($examData['questions'])->pluck('question_id');
            $missing = $questionKeys->diff($questionIds->keys());

            if ($missing->isNotEmpty()) {
                throw new RuntimeException("Mock exam {$examData['slug']}: unknown questions {$missing->implode(', ')}");
            }

            return $examData;
        })->values();

        MockExam::query()
            ->where('course_id', $course->id)
            ->whereNotIn('slug', $availableMockExams->pluck('slug'))
            ->update(['is_published' => false]);

        foreach ($availableMockExams as $examData) {
            /** @var array{slug: string, name: string, description: string, time_limit_minutes: int, passing_score: int, sort_order: int, questions: list<array{position: int, question_id: string, points: int}>} $examData */
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

            $exam->examQuestions()->whereNotIn(
                'position',
                collect($examData['questions'])->pluck('position'),
            )->delete();

            foreach ($examData['questions'] as $eq) {
                $questionId = $questionIds[$eq['question_id']]
                    ?? throw new RuntimeException("Mock exam {$examData['slug']}: unknown question {$eq['question_id']}");

                $exam->examQuestions()->updateOrCreate(
                    ['position' => $eq['position']],
                    ['question_id' => $questionId, 'points' => $eq['points']],
                );
            }
        }
    }
}
