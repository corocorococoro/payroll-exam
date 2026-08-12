<?php

use App\Models\Badge;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\GamificationSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed([ContentSeeder::class, GamificationSeeder::class]);
});

test('リリース前XP移行はアカウントを残して旧学習進捗を初期化する', function () {
    $user = User::factory()->create(['onboarded' => true]);
    $question = Question::query()->firstOrFail();
    $mockExam = MockExam::query()->firstOrFail();
    $badge = Badge::query()->firstOrFail();

    $user->statOrCreate()->update(['total_xp' => 485, 'current_streak' => 2]);
    $user->attempts()->create([
        'question_id' => $question->id,
        'lesson_id' => $question->lesson_id,
        'context' => 'lesson',
        'is_correct' => false,
        'xp_earned' => 0,
    ]);
    $user->reviewItems()->create([
        'question_id' => $question->id,
        'box' => 1,
        'due_date' => today(),
        'lapses' => 1,
    ]);
    $user->dailyActivities()->create([
        'date' => today(),
        'xp' => 20,
        'questions_answered' => 1,
        'goal_met' => true,
    ]);
    $user->dailyQuests()->create([
        'date' => today(),
        'quest_type' => 'earn_xp',
        'target' => 20,
        'progress' => 20,
        'completed' => true,
        'xp_reward' => 10,
    ]);
    $user->leagueScores()->create(['week_start' => today()->startOfWeek(), 'xp' => 20]);
    $user->badges()->attach($badge, ['awarded_at' => now()]);
    $user->mockExamAttempts()->create([
        'mock_exam_id' => $mockExam->id,
        'time_limit_minutes' => 120,
        'started_at' => now(),
    ]);
    $user->xpTransactions()->create([
        'amount' => 10,
        'source_type' => 'question',
        'source_key' => 'question:legacy',
    ]);
    $user->rewardUnlocks()->create([
        'reward_slug' => 'study-parka',
        'unlocked_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_08_12_000010_reset_prelaunch_learning_progress.php');
    $migration->up();

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue()
        ->and(User::query()->findOrFail($user->id)->onboarded)->toBeTrue();

    foreach ([
        'xp_transactions',
        'user_reward_unlocks',
        'daily_quests',
        'daily_activities',
        'league_scores',
        'user_badges',
        'review_items',
        'question_attempts',
        'lesson_progress',
        'mock_exam_attempts',
        'user_stats',
    ] as $table) {
        expect(DB::table($table)->count())->toBe(0, "{$table} was not reset");
    }
});
