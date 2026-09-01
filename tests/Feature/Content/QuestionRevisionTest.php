<?php

use App\Models\Question;
use App\Models\User;
use Database\Seeders\ContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(ContentSeeder::class);
});

test('問題内容の版が上がると旧版の習熟を無効化して即日復習へ戻す', function () {
    $user = User::factory()->create();
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $oldRevision = $question->content_revision;
    $user->questionProgresses()->create([
        'question_id' => $question->id,
        'state' => 'mastered',
        'box' => 5,
        'due_at' => now()->addMonth(),
        'lapses' => 2,
        'correct_count' => 8,
        'incorrect_count' => 2,
        'content_revision_seen' => $oldRevision,
        'first_seen_at' => now()->subMonth(),
        'last_seen_at' => now()->subDay(),
    ]);
    $user->reviewItems()->create([
        'question_id' => $question->id,
        'box' => 5,
        'due_date' => today()->addMonth(),
        'lapses' => 2,
    ]);

    $question->update(['content_revision' => $oldRevision + 1]);

    $progress = $user->questionProgresses()->where('question_id', $question->id)->firstOrFail();
    $review = $user->reviewItems()->where('question_id', $question->id)->firstOrFail();

    expect($progress->state)->toBe('learning')
        ->and($progress->box)->toBe(1)
        ->and($progress->due_at?->isToday())->toBeTrue()
        ->and($progress->first_seen_at)->toBeNull()
        ->and($progress->content_revision_seen)->toBe($oldRevision)
        ->and($review->box)->toBe(1)
        ->and($review->due_date->isToday())->toBeTrue()
        ->and($review->lapses)->toBe(2);
});

test('同じ内容の再シードでは版と習熟状態を変えない', function () {
    $user = User::factory()->create();
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $user->questionProgresses()->create([
        'question_id' => $question->id,
        'state' => 'mastered',
        'box' => 5,
        'due_at' => now()->addMonth(),
        'content_revision_seen' => $question->content_revision,
        'first_seen_at' => now()->subMonth(),
        'last_seen_at' => now()->subDay(),
    ]);

    seed(ContentSeeder::class);

    expect($question->refresh()->content_revision)->toBe(1)
        ->and($user->questionProgresses()->where('question_id', $question->id)->value('state'))->toBe('mastered');
});

test('問題改訂後は新版の初回正解だけXPを再付与する', function () {
    $user = User::factory()->create(['onboarded' => true, 'daily_goal' => 50]);
    $question = Question::where('source_id', 'q-0032')->firstOrFail();

    $first = actingAs($user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => correctChoice($question),
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk();

    $question->update(['content_revision' => $question->content_revision + 1]);
    $question->refresh();
    $this->travel(2)->seconds();

    $afterRevision = actingAs($user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => correctChoice($question),
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk();

    $this->travel(2)->seconds();
    $sameRevision = actingAs($user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => correctChoice($question),
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk();

    expect($first->json('xp_earned'))->toBe($question->difficulty->xp())
        ->and($afterRevision->json('xp_earned'))->toBe($question->difficulty->xp())
        ->and($sameRevision->json('xp_earned'))->toBe(0)
        ->and($user->attempts()->where('question_id', $question->id)->pluck('content_revision')->all())
        ->toBe([1, 2, 2]);
});
