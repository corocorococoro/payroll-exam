<?php

use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\GamificationSeeder;
use Database\Seeders\GeneratedContentSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([ContentSeeder::class, GeneratedContentSeeder::class, GamificationSeeder::class]);
});

test('解答でクエスト・報酬XP・週間リーグ・初回バッジが更新される', function () {
    $user = User::factory()->create(['daily_goal' => 20, 'onboarded' => true])->refresh();
    $lesson = Lesson::whereHas('unit', fn ($query) => $query->where('slug', 'roudou'))
        ->orderBy('sort_order')->firstOrFail();
    $questions = $lesson->questions()->limit(5)->get();
    $run = lessonRun(...$questions);

    foreach ($questions as $question) {
        $answer = $question->answer['choice'] ?? $question->answer['value'];
        actingAs($user)->withSession($run)->postJson('/answers', [
            'question_id' => $question->id,
            'answer' => (string) $answer,
            'context' => 'lesson',
            'lesson_id' => $question->lesson_id,
        ])->assertOk();
    }

    $quests = $user->dailyQuests()->whereDate('date', today())->get()->keyBy('quest_type');
    expect($quests['earn_xp']->completed)->toBeTrue()
        ->and($quests['answer_questions']->completed)->toBeTrue()
        ->and($user->leagueScores()->whereDate('week_start', today()->startOfWeek())->value('xp'))->toBeGreaterThanOrEqual(50)
        ->and($user->badges()->where('slug', 'first-step')->exists())->toBeTrue();
});

test('リーグとバッジ画面を表示できる', function () {
    $user = User::factory()->create(['onboarded' => true])->refresh();

    actingAs($user)->get('/league')->assertOk()->assertInertia(fn ($page) => $page
        ->component('league/Index')
        ->has('badges', 10)
        ->has('leaderboard'),
    );
});
