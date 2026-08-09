<?php

use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/** @return array<string, array{question_ids: list<int>, started_at: string}> */
function lessonRun(Question ...$questions): array
{
    $lessonId = $questions[0]->lesson_id;

    return ["lesson_runs.{$lessonId}" => [
        'question_ids' => collect($questions)->pluck('id')->all(),
        'started_at' => now()->subSecond()->toIso8601String(),
    ]];
}

function unlockLesson(User $user, Lesson $lesson): void
{
    $previous = Lesson::query()
        ->where('unit_id', $lesson->unit_id)
        ->where('sort_order', '<', $lesson->sort_order)
        ->get();

    foreach ($previous as $item) {
        $user->lessonProgresses()->create([
            'lesson_id' => $item->id,
            'crown_level' => 1,
            'completed_count' => 1,
            'last_completed_at' => now(),
        ]);
    }
}

function correctChoice(Question $question): string
{
    return (string) $question->answer['choice'];
}

function incorrectChoice(Question $question): string
{
    return (string) collect($question->choices)
        ->pluck('key')
        ->first(fn (string $key): bool => $key !== correctChoice($question));
}
