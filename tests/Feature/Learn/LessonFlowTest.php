<?php

use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use App\Services\LessonRunService;
use Database\Seeders\ContentSeeder;
use Illuminate\Http\Request;

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
            ->has('units', 5)
            ->where('units.0.name', '労働法・勤怠'),
        );
});

test('問題配信レスポンスに正解・解説が含まれない', function () {
    $lesson = Lesson::whereHas('unit', fn ($q) => $q->where('slug', 'roudou'))->first();

    $response = actingAs($this->user)->get("/lessons/{$lesson->id}");

    $response->assertOk();
    $expectedCount = min(LessonRunService::QUESTION_COUNT, $lesson->questions()->count());
    $response->assertInertia(fn ($page) => $page->has('questions', $expectedCount));

    $html = $response->getContent();

    expect($html)->not->toContain('common_mistake')
        ->and($html)->not->toContain('労基法24条の賃金支払5原則')
        ->and($html)->not->toContain('correct_answer')
        ->and($html)->not->toContain('source_label')
        ->and($html)->not->toContain('重点演習')
        ->and($html)->not->toContain('800問 No.');
});

test('正解するとXPが付与され解説が返る', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $correctChoice = correctChoice($question);

    $response = actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => $correctChoice,
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ]);

    $response->assertOk()
        ->assertJson([
            'correct' => true,
            'correct_answer' => $correctChoice,
            'xp_earned' => 10,
            'mastery_state' => 'review',
        ]);

    expect($response->json('explanation'))->toContain('労基法24条');

    $stat = $this->user->statOrCreate()->refresh();
    expect($stat->total_xp)->toBe(10);

    expect($this->user->attempts()->count())->toBe(1);
    expect($this->user->dailyActivities()->whereDate('date', today())->first()->xp)->toBe(10);
    expect($this->user->reviewItems()->where('question_id', $question->id)->first())
        ->box->toBe(2)
        ->due_date->isSameDay(today()->addDays(3))->toBeTrue();
    expect($this->user->questionProgresses()->where('question_id', $question->id)->first())
        ->state->toBe('review')
        ->correct_count->toBe(1);
});

test('誤答すると今日の復習キューに入りXPは0', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();

    actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => incorrectChoice($question),
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk()->assertJson(['correct' => false, 'xp_earned' => 0]);

    $item = $this->user->reviewItems()->where('question_id', $question->id)->first();

    expect($item)->not->toBeNull()
        ->and($item->box)->toBe(1)
        ->and($item->due_date->isToday())->toBeTrue();

    actingAs($this->user)->get('/review')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('review/Index')
            ->has('questions', 1)
            ->where('questions.0.id', $question->id),
        );
});

test('誤答した選択肢に対応するフィードバックを返す', function () {
    $question = Question::where('source_id', 'q-0033')->firstOrFail();
    $expectedFeedback = '回数の要件だけを見ており、一定期日払いを確認していません。';
    $choice = collect($question->distractor_feedback)->search($expectedFeedback, strict: true);

    expect($choice)->toBeString();

    actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => $choice,
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk()->assertJson([
        'correct' => false,
        'selected_feedback' => $expectedFeedback,
    ]);
});

test('再受講では未出問題を優先する', function () {
    $lesson = Lesson::where('slug', 'chingin-shiharai')->firstOrFail();
    $request = Request::create("/lessons/{$lesson->id}");
    $request->setUserResolver(fn () => $this->user);
    $request->setLaravelSession(app('session')->driver());
    $service = app(LessonRunService::class);

    $first = $service->getOrStart($request, $lesson);
    foreach ($first['question_ids'] as $questionId) {
        $this->user->attempts()->create([
            'question_id' => $questionId,
            'lesson_id' => $lesson->id,
            'context' => 'lesson',
            'is_correct' => true,
            'given_answer' => ['given' => 'A'],
            'xp_earned' => 0,
        ]);
    }

    $service->clear($request, $lesson);
    $second = $service->getOrStart($request, $lesson);

    expect(array_intersect($first['question_ids'], $second['question_ids']))->toBe([]);
});

test('有限回のレッスン受講ですべての公開問題へ到達できる', function () {
    $service = app(LessonRunService::class);
    $reached = collect();

    foreach (Lesson::query()->orderBy('id')->get() as $lesson) {
        $request = Request::create("/lessons/{$lesson->id}");
        $request->setUserResolver(fn () => $this->user);
        $request->setLaravelSession(app('session')->driver());
        $expectedIds = $lesson->questions()->pluck('id')->sort()->values();
        $lessonReached = collect();
        $maximumRuns = $expectedIds->count() + 1;

        for ($runNumber = 0; $lessonReached->count() < $expectedIds->count(); $runNumber++) {
            expect($runNumber)->toBeLessThan($maximumRuns, "{$lesson->slug}で全問題へ到達できません");
            $run = $service->getOrStart($request, $lesson);
            expect($run['question_ids'])->not->toBeEmpty();

            foreach ($run['question_ids'] as $questionId) {
                $this->user->attempts()->create([
                    'question_id' => $questionId,
                    'lesson_id' => $lesson->id,
                    'context' => 'lesson',
                    'is_correct' => true,
                    'given_answer' => ['given' => 'A'],
                    'xp_earned' => 0,
                ]);
            }

            $lessonReached = $lessonReached->merge($run['question_ids'])->unique()->values();
            $service->clear($request, $lesson);
        }

        expect($lessonReached->sort()->values()->all())->toBe($expectedIds->all());
        $reached = $reached->merge($lessonReached);
    }

    expect($reached->unique())->toHaveCount(Question::query()->published()->count());
});

test('復習は期限到来数を保ったまま20問ずつ出題する', function () {
    $questions = Question::query()->published()->limit(25)->get();

    foreach ($questions as $question) {
        $this->user->reviewItems()->create([
            'question_id' => $question->id,
            'box' => 1,
            'due_date' => today(),
            'lapses' => 1,
        ]);
    }

    actingAs($this->user)->get('/review')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('due_total', 25)
            ->has('questions', 20),
        );
});

test('数値入力はカンマ・全角数字でも判定できる', function () {
    $question = Question::where('source_id', 'q-0831')->firstOrFail();
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
    $question = Question::where('source_id', 'q-0032')->firstOrFail();

    actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => correctChoice($question),
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk();

    $stat = $this->user->statOrCreate()->refresh();

    expect($stat->current_streak)->toBe(1)
        ->and($stat->last_active_date->isToday())->toBeTrue();

    expect($this->user->dailyActivities()->whereDate('date', today())->first()->goal_met)->toBeTrue();
});

test('出題されていない問題とレッスンIDの偽装を拒否する', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $other = Question::where('lesson_id', $question->lesson_id)->whereKeyNot($question->id)->firstOrFail();

    actingAs($this->user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $other->id,
        'answer' => 'A',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertStatus(422);
});

test('同じ出題への二重解答ではXPを再獲得できない', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $run = lessonRun($question);
    $payload = [
        'question_id' => $question->id,
        'answer' => correctChoice($question),
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ];

    actingAs($this->user)->withSession($run)->postJson('/answers', $payload)->assertOk();
    actingAs($this->user)->withSession($run)->postJson('/answers', $payload)->assertStatus(422);

    expect($this->user->statOrCreate()->refresh()->total_xp)->toBe(10);
});

test('レビュー期限切れの問題は古いレッスンセッションからも解答できない', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $run = lessonRun($question);
    $question->update(['review_due_at' => now()->subMinute()]);

    actingAs($this->user)->withSession($run)->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => 'D',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertNotFound();

    expect($this->user->attempts()->count())->toBe(0);
});

test('同じレッスンセッションを二重完了してもボーナスを再獲得できない', function () {
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

    $xpBeforeCompletion = $this->user->statOrCreate()->refresh()->total_xp;
    actingAs($this->user)->withSession($run)->postJson("/lessons/{$lesson->id}/complete")->assertOk();
    $xpAfterCompletion = $this->user->statOrCreate()->refresh()->total_xp;
    actingAs($this->user)->withSession($run)->postJson("/lessons/{$lesson->id}/complete")->assertStatus(422);

    $progress = $this->user->lessonProgresses()->where('lesson_id', $lesson->id)->firstOrFail();
    expect($progress->completed_count)->toBe(1)
        ->and($xpAfterCompletion)->toBeGreaterThan($xpBeforeCompletion)
        ->and($this->user->statOrCreate()->refresh()->total_xp)->toBe($xpAfterCompletion);
});

test('期限の来ていない問題を復習として送信できない', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();

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
