<?php

namespace App\Services;

use App\Models\LearningModule;
use App\Models\LearningModuleProgress;
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

    /** @return array<string, mixed> */
    public function overview(User $user): array
    {
        $modules = LearningModule::where('is_active', true)->orderBy('position')
            ->with(['questions' => fn ($query) => $query->published()->practiceBank()])->get();
        $progresses = LearningModuleProgress::where('user_id', $user->id)->get()->keyBy('learning_module_id');
        $passedSlugs = $modules->filter(function (LearningModule $module) use ($progresses): bool {
            $progress = $progresses->get($module->id);

            return $progress?->content_hash === $module->content_hash && $progress->independent_passed_at !== null;
        })->pluck('slug')->all();
        $names = $modules->pluck('name', 'slug');
        $rows = $modules->map(function (LearningModule $module) use ($progresses, $passedSlugs, $names): array {
            $progress = $progresses->get($module->id);
            $current = $progress?->content_hash === $module->content_hash;
            $passed = $current && $progress->independent_passed_at !== null;
            $due = $passed && ($progress->review_due_at?->isPast() ?? false);
            $phase = $this->phase($progress, $module);

            return [
                'id' => $module->id, 'slug' => $module->slug, 'name' => $module->name,
                'section' => $module->section, 'goal' => $module->goal, 'method' => $module->method,
                'phase' => $phase, 'passed' => $passed,
                'retained' => $current && $progress->spaced_passed_at !== null,
                'due' => $due, 'needs_support' => $current && $progress->needs_support,
                'question_count' => $module->questions->count(),
                'session_question_count' => $phase === 'guided'
                    ? min(3, max(1, $module->questions->count() - 2)) : min(5, $module->questions->count()),
                'prerequisite_names' => array_values(array_map(fn (string $slug): string => (string) $names[$slug],
                    array_diff($module->prerequisites, $passedSlugs))),
                'href' => "/study/{$module->slug}",
            ];
        })->values();
        $next = $rows->first(fn (array $row): bool => ! $row['passed'] && $row['prerequisite_names'] === []);
        $next ??= $rows->first(fn (array $row): bool => ! $row['passed']);
        $review = $rows->firstWhere('due', true);
        $sections = File::json(database_path('seeders/data/learning-curriculum.json'))['sections'];

        return [
            'sections' => $sections, 'modules' => $rows->all(), 'next' => $next, 'review' => $review,
            'passed_count' => $rows->where('passed', true)->count(),
            'retained_count' => $rows->where('retained', true)->count(),
            'module_count' => $rows->count(), 'due_count' => $rows->where('due', true)->count(),
        ];
    }
}
