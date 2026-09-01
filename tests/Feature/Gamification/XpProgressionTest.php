<?php

use App\Enums\AttemptContext;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;
use App\Services\DailyQuestService;
use App\Services\XpLevelService;
use App\Services\XpService;
use Database\Seeders\ContentSeeder;
use Database\Seeders\GamificationSeeder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed([ContentSeeder::class, GamificationSeeder::class]);
});

test('XP台帳は同じ発生源を一度だけ集計する', function () {
    $user = User::factory()->create(['onboarded' => true, 'daily_goal' => 20]);
    $xp = app(XpService::class);

    expect($xp->award($user, 10, 'question', 'question:1'))->not->toBeNull()
        ->and($xp->award($user, 10, 'question', 'question:1'))->toBeNull()
        ->and($user->xpTransactions()->count())->toBe(1)
        ->and($user->xpTransactions()->sum('amount'))->toBe(10)
        ->and($user->statOrCreate()->total_xp)->toBe(10)
        ->and($user->dailyActivities()->whereDate('date', today())->value('xp'))->toBe(10)
        ->and($user->leagueScores()->whereDate('week_start', today()->startOfWeek())->value('xp'))->toBe(10);
});

test('正解済み問題の通常周回は直接XPを再付与しない', function () {
    $user = User::factory()->create(['onboarded' => true, 'daily_goal' => 50]);
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $firstRun = lessonRun($question);

    $first = actingAs($user)->withSession($firstRun)->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => correctChoice($question),
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk();

    $this->travel(2)->seconds();
    $secondRun = lessonRun($question);
    $second = actingAs($user)->withSession($secondRun)->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => correctChoice($question),
        'context' => 'lesson',
        'lesson_id' => $question->lesson_id,
    ])->assertOk();

    expect($first->json('xp_earned'))->toBe($question->difficulty->xp())
        ->and($first->json('xp_status'))->toBe('earned')
        ->and($second->json('xp_earned'))->toBe(0)
        ->and($second->json('xp_status'))->toBe('already_credited')
        ->and($user->attempts()->where('question_id', $question->id)->sum('xp_earned'))->toBe($question->difficulty->xp());
});

test('正解履歴があっても期限到来した復習は満額XPになる', function () {
    $user = User::factory()->create(['onboarded' => true, 'daily_goal' => 50]);
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    QuestionAttempt::create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'lesson_id' => $question->lesson_id,
        'context' => AttemptContext::Lesson,
        'is_correct' => true,
        'xp_earned' => $question->difficulty->xp(),
    ]);
    $user->reviewItems()->create([
        'question_id' => $question->id,
        'box' => 2,
        'due_date' => today(),
        'lapses' => 1,
    ]);

    actingAs($user)->postJson('/answers', [
        'question_id' => $question->id,
        'answer' => correctChoice($question),
        'context' => 'review',
    ])->assertOk()
        ->assertJsonPath('xp_status', 'earned')
        ->assertJsonPath('xp_earned', $question->difficulty->xp());
});

test('復習対象がなければ達成可能なレッスンクエストを生成する', function () {
    $user = User::factory()->create(['onboarded' => true, 'daily_goal' => 20]);
    $quests = app(DailyQuestService::class)->ensureToday($user)->keyBy('quest_type');

    expect($quests)->toHaveKey('complete_lesson')
        ->and($quests)->not->toHaveKey('review_correct');
});

test('復習対象が3問未満なら存在件数をクエスト目標にする', function () {
    $user = User::factory()->create(['onboarded' => true, 'daily_goal' => 20]);
    $questions = Question::limit(2)->get();

    foreach ($questions as $question) {
        $user->reviewItems()->create([
            'question_id' => $question->id,
            'box' => 1,
            'due_date' => today(),
            'lapses' => 1,
        ]);
    }

    $quest = app(DailyQuestService::class)->ensureToday($user)->firstWhere('quest_type', 'review_correct');

    expect($quest)->not->toBeNull()
        ->and($quest->target)->toBe(2);
});

test('レベル境界で衣装を解放し未解放衣装の装備を拒否する', function () {
    $user = User::factory()->create(['onboarded' => true]);
    $xp = app(XpService::class);
    $levels = app(XpLevelService::class);
    $xp->award($user, 250, 'question', 'question:level-three');
    $levels->syncRewardUnlocks($user);

    expect($levels->progress($user))
        ->level->toBe(3)
        ->title->toBe('いつもの相棒')
        ->xp_to_next->toBe(250)
        ->and($user->rewardUnlocks()->where('reward_slug', 'mint-overalls')->exists())->toBeTrue()
        ->and($user->rewardUnlocks()->where('reward_slug', 'cozy-study')->exists())->toBeTrue()
        ->and($user->rewardUnlocks()->where('reward_slug', 'cozy-pajamas')->exists())->toBeTrue()
        ->and($user->rewardUnlocks()->where('reward_slug', 'study-parka')->exists())->toBeTrue()
        ->and($user->rewardUnlocks()->where('reward_slug', 'sunny-raincoat')->exists())->toBeTrue();

    actingAs($user)->patchJson('/rewards/mascot-style', ['style' => 'payroll-cardigan'])
        ->assertUnprocessable();
    actingAs($user)->patchJson('/rewards/mascot-style', ['style' => 'study-parka'])
        ->assertOk()
        ->assertJsonPath('xp_progress.mascot_style', 'study-parka');
    actingAs($user)->patchJson('/rewards/mascot-style', ['style' => 'sunny-raincoat'])
        ->assertOk()
        ->assertJsonPath('xp_progress.mascot_style', 'sunny-raincoat');
});

test('成長画面にレベル進捗と衣装一覧を返す', function () {
    $user = User::factory()->create(['onboarded' => true]);

    actingAs($user)->get('/league')->assertOk()->assertInertia(fn ($page) => $page
        ->component('league/Index')
        ->where('xp_progress.level', 1)
        ->has('styles', 20)
        ->where('styles.1.slug', 'mint-overalls')
        ->where('styles.1.unlocked', true)
        ->has('levels', 10)
        ->has('badges', 10)
        ->has('leaderboard')
    );
});

test('最高レベルでは全20着を解放して追加衣装を装備できる', function () {
    $user = User::factory()->create(['onboarded' => true]);
    $xp = app(XpService::class);
    $levels = app(XpLevelService::class);
    $xp->award($user, 5200, 'question', 'question:max-style-catalog');
    $levels->syncRewardUnlocks($user);

    $styles = collect($levels->styles($user));

    expect($user->rewardUnlocks)->toHaveCount(19)
        ->and($styles)->toHaveCount(20)
        ->and($styles->every(fn (array $style): bool => $style['unlocked']))->toBeTrue();

    actingAs($user)->patchJson('/rewards/mascot-style', ['style' => 'celebration-hakama'])
        ->assertOk()
        ->assertJsonPath('xp_progress.mascot_style', 'celebration-hakama');
});
