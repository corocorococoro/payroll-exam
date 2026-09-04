<?php

use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\GamificationSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([ContentSeeder::class, GamificationSeeder::class]);
});

test('解答でクエスト・報酬XP・週間リーグ・初回バッジが更新される', function () {
    $user = User::factory()->create(['daily_goal' => 20, 'onboarded' => true])->refresh();
    $lesson = Lesson::where('slug', 'kyuyo-keisan')->firstOrFail();
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
        ->where('badges.3.description', '7日連続で目標を達成した')
        ->where('badges.6.name', 'レッスン完了の達人')
        ->where('badges.8.name', '80点突破')
        ->has('leaderboard'),
    );
});

test('既存のバッジ文言を分かりやすい表現へ更新できる', function () {
    $migration = require database_path('migrations/2026_09_04_000000_update_gamification_copy.php');

    $migration->down();
    expect(DB::table('badges')->where('slug', 'lesson-master')->value('name'))
        ->toBe('クラウンマスター');

    $migration->up();
    expect(DB::table('badges')->where('slug', 'streak-7')->value('description'))
        ->toBe('7日連続で目標を達成した')
        ->and(DB::table('badges')->where('slug', 'lesson-master')->value('name'))
        ->toBe('レッスン完了の達人')
        ->and(DB::table('badges')->where('slug', 'mock-80')->value('name'))
        ->toBe('80点突破');
});
