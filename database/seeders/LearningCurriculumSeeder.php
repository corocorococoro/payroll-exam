<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LearningModule;
use App\Models\Question;
use App\Services\QuestionReviewLedger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use RuntimeException;

class LearningCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $data = File::json(database_path('seeders/data/learning-curriculum.json'));
        $modules = $data['modules'];
        $questions = Question::query()->published()->get()->keyBy('source_id');
        $practiceIds = Question::query()->published()->practiceBank()->pluck('source_id')->all();
        $sections = array_column($data['sections'], 'slug');
        $slugs = [];
        $assigned = [];

        // Validate the entire route before replacing any module or pivot.
        foreach ($modules as $module) {
            if (in_array($module['slug'], $slugs, true) || ! in_array($module['section'], $sections, true)
                || array_diff($module['prerequisites'], $slugs) !== []
                || ! in_array($module['method'], ['understand', 'remember', 'calculate'], true)
                || count($module['approach']) !== 3
                || trim($module['goal']) === '' || trim($module['why']) === ''
                || ! in_array($module['example_source_id'], $module['question_source_ids'], true)
                || ! in_array($module['example_source_id'], $practiceIds, true)) {
                throw new RuntimeException("Invalid learning module {$module['slug']}");
            }
            $slugs[] = $module['slug'];
            foreach ($module['question_source_ids'] as $id) {
                if (! $questions->has($id) || in_array($id, $assigned, true)) {
                    throw new RuntimeException("Unknown or duplicate curriculum question {$id}");
                }
                $assigned[] = $id;
            }
        }
        if (array_diff($questions->keys()->all(), $assigned) !== []) {
            throw new RuntimeException('Published questions are missing from the curriculum.');
        }

        $course = Course::where('slug', 'kyuyo-2kyu')->firstOrFail();
        foreach ($modules as $module) {
            $revisions = array_map(fn (string $id): array => [
                $id, $questions[$id]->content_revision, $questions[$id]->review_fingerprint,
            ], $module['question_source_ids']);
            $attributes = $module;
            unset($attributes['example_source_id'], $attributes['question_source_ids']);
            $attributes['course_id'] = $course->id;
            $attributes['example_question_id'] = $questions[$module['example_source_id']]->id;
            $attributes['content_hash'] = QuestionReviewLedger::fingerprint([$module, $revisions]);
            $attributes['is_active'] = true;
            $record = LearningModule::updateOrCreate(['slug' => $module['slug']], $attributes);
            $pivot = [];
            foreach ($module['question_source_ids'] as $position => $id) {
                $pivot[$questions[$id]->id] = ['position' => $position + 1];
            }
            $record->questions()->sync($pivot);
        }
        // Keep history for retired modules; they no longer appear or start runs.
        LearningModule::whereNotIn('slug', $slugs)->update(['is_active' => false]);
    }
}
