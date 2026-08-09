<?php

use App\Models\Question;
use App\Models\ReviewItem;
use App\Models\User;
use App\Notifications\StudyReminder;
use App\Services\StreakService;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\seed;

beforeEach(function () {
    Carbon::setTestNow('2026-08-09 12:00:00');
    seed(ContentSeeder::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('初回ダッシュボードではオンボーディングを表示し設定を保存できる', function () {
    $user = User::factory()->create()->refresh();

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Onboarding')
            ->where('defaults.daily_goal', 20),
        );

    actingAs($user)->post('/onboarding', [
        'daily_goal' => 30,
        'reminder_enabled' => true,
        'reminder_time' => '19:30',
        'exam_date' => '2026-11-22',
    ])->assertRedirect('/dashboard');

    $user->refresh();

    expect($user->onboarded)->toBeTrue()
        ->and($user->daily_goal)->toBe(30)
        ->and(substr((string) $user->reminder_time, 0, 5))->toBe('19:30');
});

test('ダッシュボードに習慣ループの集計を表示する', function () {
    $user = User::factory()->create(['onboarded' => true, 'daily_goal' => 20])->refresh();
    $user->dailyActivities()->create([
        'date' => today(),
        'xp' => 20,
        'questions_answered' => 2,
        'goal_met' => true,
    ]);
    $user->statOrCreate()->update(['total_xp' => 80, 'current_streak' => 3, 'longest_streak' => 4]);

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('summary.today_xp', 20)
            ->where('summary.current_streak', 3)
            ->has('heatmap', 84)
            ->has('accuracy_by_unit', 6)
            ->has('season.phases', 5),
        );
});

test('誤答も今日の解答数に数え、復習画面に正解を漏らさない', function () {
    $user = User::factory()->create(['onboarded' => true])->refresh();
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();

    actingAs($user)->withSession(lessonRun($question))->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => 'A',
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk()->assertJson(['correct' => false]);

    expect($user->dailyActivities()->whereDate('date', today())->first()->questions_answered)->toBe(1);

    $item = $user->reviewItems()->firstOrFail();
    $item->update(['due_date' => today()]);

    $response = actingAs($user)->get('/review');

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('review/Index')
        ->has('questions', 1)
        ->missing('questions.0.answer')
        ->missing('questions.0.explanation'),
    );

    expect($response->getContent())->not->toContain('労基法24条の賃金支払5原則');
});

test('復習で正解するとLeitnerボックスと期限が進む', function () {
    $user = User::factory()->create(['onboarded' => true])->refresh();
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();
    $item = ReviewItem::create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'box' => 1,
        'due_date' => today(),
        'lapses' => 1,
    ]);

    actingAs($user)->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => 'D',
        'context' => 'review',
    ])->assertOk()->assertJson(['correct' => true]);

    $item->refresh();

    expect($item->box)->toBe(2)
        ->and($item->due_date->isSameDay(today()->addDays(3)))->toBeTrue();
});

test('日次判定はフリーズを消費し、その後の未達でストリークを切る', function () {
    $user = User::factory()->create()->refresh();
    $user->statOrCreate()->update([
        'current_streak' => 5,
        'longest_streak' => 5,
        'last_active_date' => today()->subDays(2),
        'streak_freezes' => 1,
    ]);

    $service = app(StreakService::class);
    $service->applyOvernightCheck($user);
    $stat = $user->statOrCreate()->refresh();

    expect($stat->current_streak)->toBe(5)
        ->and($stat->streak_freezes)->toBe(0)
        ->and($stat->last_active_date->isSameDay(today()->subDay()))->toBeTrue();

    Carbon::setTestNow('2026-08-10 00:05:00');
    $service->applyOvernightCheck($user);

    expect($stat->refresh()->current_streak)->toBe(0);
});

test('未達成ユーザーに設定時刻でリマインダーを一日一度送る', function () {
    Notification::fake();
    Carbon::setTestNow('2026-08-09 20:00:00');

    $user = User::factory()->create([
        'reminder_enabled' => true,
        'reminder_time' => '20:00',
    ])->refresh();

    artisan('reminders:send')->assertSuccessful();
    artisan('reminders:send')->assertSuccessful();

    Notification::assertSentToTimes($user, StudyReminder::class, 1);
    expect($user->refresh()->last_reminded_on->isToday())->toBeTrue();
});

test('ゴール達成済みならリマインダーを送らない', function () {
    Notification::fake();
    Carbon::setTestNow('2026-08-09 20:00:00');

    $user = User::factory()->create([
        'reminder_enabled' => true,
        'reminder_time' => '20:00',
    ])->refresh();
    $user->dailyActivities()->create([
        'date' => today(),
        'xp' => 20,
        'questions_answered' => 2,
        'goal_met' => true,
    ]);

    artisan('reminders:send')->assertSuccessful();

    Notification::assertNothingSent();
});
