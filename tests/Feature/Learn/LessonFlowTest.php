<?php

use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(ContentSeeder::class);
    // refresh: DBデフォルト値(daily_goal等)を属性に反映させる
    $this->user = User::factory()->create()->refresh();
});

test('スキルツリーが表示され、ユニットとレッスンが並ぶ', function () {
    actingAs($this->user)
        ->get('/learn')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('learn/Index')
            ->has('units', 6)
            ->where('units.0.name', '労働法・勤怠'),
        );
});

test('問題配信レスポンスに正解・解説が含まれない', function () {
    $lesson = Lesson::whereHas('unit', fn ($q) => $q->where('slug', 'roudou'))->first();

    $response = actingAs($this->user)->get("/lessons/{$lesson->id}");

    $response->assertOk();
    $expectedCount = min(7, $lesson->questions()->count());
    $response->assertInertia(fn ($page) => $page->has('questions', $expectedCount));

    $html = $response->getContent();

    expect($html)->not->toContain('common_mistake')
        ->and($html)->not->toContain('労基法24条の賃金支払5原則')
        ->and($html)->not->toContain('correct_answer');
});

test('正解するとXPが付与され解説が返る', function () {
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();

    $response = actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => 'D',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ]);

    $response->assertOk()
        ->assertJson(['correct' => true, 'correct_answer' => 'D', 'xp_earned' => 10]);

    expect($response->json('explanation'))->toContain('労基法24条');

    $stat = $this->user->statOrCreate()->refresh();
    expect($stat->total_xp)->toBe(10);

    expect($this->user->attempts()->count())->toBe(1);
    expect($this->user->dailyActivities()->whereDate('date', today())->first()->xp)->toBe(10);
});

test('誤答すると復習キューに入りXPは0', function () {
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();

    actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => 'A',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk()->assertJson(['correct' => false, 'xp_earned' => 0]);

    $item = $this->user->reviewItems()->where('question_id', $question->id)->first();

    expect($item)->not->toBeNull()
        ->and($item->box)->toBe(1)
        ->and($item->due_date->isSameDay(today()->addDay()))->toBeTrue();
});

test('数値入力はカンマ・全角数字でも判定できる', function () {
    $question = Question::where('source_id', 'r2-q48')->firstOrFail();
    unlockLesson($this->user, $question->lesson);

    actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => '４５，０００円',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk()->assertJson(['correct' => true]);
});

test('全問解答するとレッスン完了でクラウンが上がる', function () {
    $lesson = Lesson::whereHas('unit', fn ($q) => $q->where('slug', 'shikyu'))->firstOrFail();
    $questions = $lesson->questions()->limit(7)->get();
    $run = lessonRun(...$questions);

    foreach ($questions as $question) {
        actingAs($this->user)->withSession($run)->postJson('/answers', [
            'question_id' => $question->id,
            'answer' => 'A',
            'context' => 'lesson',
            'lesson_id' => $lesson->id,
        ])->assertOk();
    }

    $response = actingAs($this->user)->withSession($run)->postJson("/lessons/{$lesson->id}/complete");

    $response->assertOk()->assertJson(['crown_level' => 1, 'bonus_xp' => 10]);

    expect($this->user->lessonProgresses()->where('lesson_id', $lesson->id)->first()->crown_level)->toBe(1);
});

test('解答していないレッスンは完了できない', function () {
    $lesson = Lesson::whereHas('unit', fn ($q) => $q->where('slug', 'shikyu'))->firstOrFail();

    actingAs($this->user)->withSession(lessonRun($lesson->questions()->firstOrFail()))
        ->postJson("/lessons/{$lesson->id}/complete")->assertStatus(422);
});

test('デイリーゴール達成でストリークが記録される', function () {
    $this->user->update(['daily_goal' => 10]);
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();

    actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => 'D',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk();

    $stat = $this->user->statOrCreate()->refresh();

    expect($stat->current_streak)->toBe(1)
        ->and($stat->last_active_date->isToday())->toBeTrue();

    expect($this->user->dailyActivities()->whereDate('date', today())->first()->goal_met)->toBeTrue();
});

test('出題されていない問題とレッスンIDの偽装を拒否する', function () {
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();
    $other = Question::where('lesson_id', $question->lesson_id)->whereKeyNot($question->id)->firstOrFail();

    actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $other->id,
        'answer' => 'A',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertStatus(422);
});

test('同じ出題への二重解答ではXPを再獲得できない', function () {
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();
    $run = lessonRun($question);
    $payload = [
        'question_id' => $question->id,
        'answer' => 'D',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ];

    actingAs($this->user)->withSession($run)->postJson('/answers', $payload)->assertOk();
    actingAs($this->user)->withSession($run)->postJson('/answers', $payload)->assertStatus(422);

    expect($this->user->statOrCreate()->refresh()->total_xp)->toBe(10);
});

test('期限の来ていない問題を復習として送信できない', function () {
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();

    actingAs($this->user)->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => 'D',
        'context' => 'review',
    ])->assertStatus(422);
});

test('ロック中レッスンへの直接アクセスを拒否する', function () {
    $lesson = Lesson::where('sort_order', '>', 1)->firstOrFail();

    actingAs($this->user)->get("/lessons/{$lesson->id}")->assertForbidden();
});
