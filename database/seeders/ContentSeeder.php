<?php

namespace Database\Seeders;

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

                Question::updateOrCreate(
                    ['source_id' => $q['source_id']],
                    [
                        'unit_id' => $units[$q['unit']] ?? throw new RuntimeException("Unknown unit {$q['unit']}"),
                        'lesson_id' => $q['lesson'] !== null
                            ? ($lessons[$lessonKey] ?? throw new RuntimeException("Unknown lesson {$lessonKey}"))->id
                            : null,
                        'type' => $q['type'],
                        'category' => $q['category'],
                        'difficulty' => $q['difficulty'],
                        'fiscal_year' => self::FISCAL_YEAR,
                        'question_text' => $q['question_text'],
                        'choices' => $q['choices'],
                        'answer' => $q['answer'],
                        'explanation' => $q['explanation'],
                        'common_mistake' => $q['common_mistake'],
                        'calc_params' => $q['calc_params'],
                        'reference_sheet_slugs' => $q['reference_sheet_slugs'],
                        'is_active' => true,
                    ],
                );
            }
        }
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
