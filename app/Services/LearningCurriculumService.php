<?php

namespace App\Services;

use App\Models\LearningModule;
use App\Models\LearningModuleProgress;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\File;

class LearningCurriculumService
{
    public function progress(User $user, LearningModule $module): LearningModuleProgress
    {
        $progress = LearningModuleProgress::firstOrCreate(
            ['user_id' => $user->id, 'learning_module_id' => $module->id],
            ['content_hash' => $module->content_hash],
        );
        if ($progress->content_hash !== $module->content_hash) {
            $progress->update([
                'content_hash' => $module->content_hash, 'guided_completed_at' => null,
                'independent_passed_at' => null, 'spaced_passed_at' => null,
                'review_due_at' => null, 'needs_support' => false,
                'independent_concepts' => [], 'spaced_concepts' => [],
            ]);
        }

        return $progress;
    }

    public function phase(?LearningModuleProgress $progress, LearningModule $module): string
    {
        if ($progress === null || $progress->content_hash !== $module->content_hash
            || $progress->guided_completed_at === null || $progress->needs_support) {
            return 'guided';
        }
        if ($progress->independent_passed_at === null) {
            return 'check';
        }

        return 'spaced';
    }

    public function available(LearningModule $module): bool
    {
        $total = $module->questions()->count();

        return $total > 0 && $module->questions()->published()->count() === $total
            && $module->example_question_id !== null
            && $module->questions()->published()->practiceBank()->whereKey($module->example_question_id)->exists();
    }

    /** @return list<string> */
    public function concepts(LearningModule $module): array
    {
        $concepts = [];
        foreach ($module->questions()->published()->practiceBank()->get(['questions.concept_key']) as $question) {
            if ($question->concept_key !== null && ! in_array($question->concept_key, $concepts, true)) {
                $concepts[] = $question->concept_key;
            }
        }

        return $concepts;
    }

    /** @return array<string, mixed> */
    public function overview(User $user): array
    {
        $modules = LearningModule::where('is_active', true)->orderBy('position')
            ->with('questions:id,concept_key')->get();
        $publishedIds = Question::query()->published()->pluck('id')->all();
        $practiceIds = Question::query()->practiceAvailableFor($user)->pluck('id')->all();
        $progresses = LearningModuleProgress::where('user_id', $user->id)->get()->keyBy('learning_module_id');
        $passedSlugs = $modules->filter(function (LearningModule $module) use ($progresses, $publishedIds): bool {
            $progress = $progresses->get($module->id);

            return $module->questions->isNotEmpty()
                && array_diff($module->questions->modelKeys(), $publishedIds) === []
                && $progress?->content_hash === $module->content_hash && $progress->independent_passed_at !== null;
        })->pluck('slug')->all();
        $names = $modules->pluck('name', 'slug');
        $rows = $modules->map(function (LearningModule $module) use ($progresses, $passedSlugs, $names, $publishedIds, $practiceIds): array {
            $progress = $progresses->get($module->id);
            $available = $module->questions->isNotEmpty()
                && array_diff($module->questions->modelKeys(), $publishedIds) === [];
            $count = $module->questions->whereIn('id', $practiceIds)->count();
            $current = $available && $progress?->content_hash === $module->content_hash;
            $passed = $current && $progress->independent_passed_at !== null;
            $due = $passed && ($progress->review_due_at?->isPast() ?? false);
            $phase = $this->phase($progress, $module);

            return [
                'id' => $module->id, 'slug' => $module->slug, 'name' => $module->name,
                'section' => $module->section, 'goal' => $module->goal, 'method' => $module->method,
                'phase' => $phase, 'passed' => $passed, 'available' => $available,
                'retained' => $current && $progress->spaced_passed_at !== null,
                'due' => $due, 'needs_support' => $current && $progress->needs_support,
                'question_count' => $count,
                'session_question_count' => ! $available ? 0 : ($phase === 'guided'
                    ? min(3, max(1, $count - 2)) : min(5, $count)),
                'prerequisite_names' => array_values(array_map(fn (string $slug): string => (string) $names[$slug],
                    array_diff($module->prerequisites, $passedSlugs))),
                'href' => "/study/{$module->slug}",
            ];
        })->values();
        $next = $rows->first(fn (array $row): bool => $row['available'] && ! $row['passed'] && $row['prerequisite_names'] === []);
        $next ??= $rows->first(fn (array $row): bool => $row['available'] && ! $row['passed']);
        $review = $rows->firstWhere('due', true);
        $sections = File::json(database_path('seeders/data/learning-curriculum.json'))['sections'];

        return [
            'sections' => $sections, 'modules' => $rows->all(), 'next' => $next, 'review' => $review,
            'passed_count' => $rows->where('passed', true)->count(),
            'retained_count' => $rows->where('retained', true)->count(),
            'module_count' => $rows->count(), 'unavailable_count' => $rows->where('available', false)->count(), 'due_count' => $rows->where('due', true)->count(),
        ];
    }
}
