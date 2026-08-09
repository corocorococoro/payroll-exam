<?php

namespace Database\Seeders;

use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
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

        foreach ($data['units'] as $unitData) {
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

            foreach ($unitData['lessons'] as $lessonData) {
                Lesson::updateOrCreate(
                    ['unit_id' => $unit->id, 'slug' => $lessonData['slug']],
                    [
                        'name' => $lessonData['name'],
                        'description' => $lessonData['description'],
                        'sort_order' => $lessonData['sort_order'],
                    ],
                );
            }
        }
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

        foreach (File::files($this->dataPath('questions')) as $file) {
            foreach (File::json($file->getPathname()) as $q) {
                $this->validateQuestion($q);

                $lessonKey = $q['unit'].'/'.$q['lesson'];
                $content = [
                    'type' => $q['type'],
                    'question_text' => $q['question_text'],
                    'choices' => $q['choices'],
                    'answer' => $q['answer'],
                    'explanation' => $q['explanation'],
                    'common_mistake' => $q['common_mistake'],
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
                        'concept_key' => $q['concept_key'] ?? $q['source_id'],
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
                        'calc_params' => $content['calc_params'],
                        'reference_sheet_slugs' => $q['reference_sheet_slugs'],
                        'source_urls' => $q['source_urls'] ?? $this->sourceUrls($q['source_id']),
                        'review_notes' => $q['review_notes'] ?? '2026年度の公表一次資料で確認。試験基準日の2026-09-01経過後に再レビューする。',
                        'reviewed_at' => $q['reviewed_at'] ?? '2026-08-09 00:00:00',
                        'review_due_at' => $q['review_due_at'] ?? '2026-09-02 00:00:00',
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    /** @return list<string> */
    private function sourceUrls(string $sourceId): array
    {
        $number = (int) substr($sourceId, 4);
        $exam = 'https://jitsumu-up.jp/about/';

        return match (true) {
            $number <= 11, $number === 36 => [
                $exam,
                'https://www.mhlw.go.jp/stf/seisakunitsuite/bunya/koyou_roudou/roudoukijun/index.html',
            ],
            $number <= 15, $number === 40 => [
                $exam,
                'https://www.nta.go.jp/users/gensen/2026tsukin/index.htm',
            ],
            $number <= 21, in_array($number, [38, 39], true) => [
                $exam,
                'https://www.nta.go.jp/publication/pamph/gensen/zeigakuhyo2026/data/all.pdf',
            ],
            $number <= 35, in_array($number, [37, 48], true) => [
                $exam,
                'https://www.kyoukaikenpo.or.jp/about/business/insurance_rate/rate_prefectures/r08/index.html',
                'https://www.nenkin.go.jp/service/kounen/hokenryo/hoshu/20150515-01.html',
            ],
            default => [
                $exam,
                'https://www.nta.go.jp/users/gensen/2026kiso/index.htm',
            ],
        };
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

            if (count($keys) < 2) {
                throw new RuntimeException("Question {$id}: choice question needs at least 2 choices");
            }

            if (! in_array($q['answer']['choice'] ?? null, $keys, true)) {
                throw new RuntimeException("Question {$id}: correct choice not found in choices");
            }
        }

        if ($q['type'] === QuestionType::Numeric->value && ! is_numeric($q['answer']['value'] ?? null)) {
            throw new RuntimeException("Question {$id}: numeric question needs answer.value");
        }
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
