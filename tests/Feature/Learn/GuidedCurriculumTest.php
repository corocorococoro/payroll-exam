<?php

use App\Models\LearningModule;
use App\Models\LearningModuleProgress;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function () {
    seed(ContentSeeder::class);
    $this->user = User::factory()->create(['onboarded' => true])->refresh();
});

function openStudy(User $user, string $slug = 'payslip', string $mode = ''): array
{
    $response = actingAs($user)->get('/study/'.$slug.($mode === '' ? '' : '?mode='.$mode))->assertOk();
    $module = LearningModule::where('slug', $slug)->firstOrFail();

    return [$module, session("study_runs.{$module->id}")];
}

function answerStudy(User $user, LearningModule $module, array $run, bool $correct = true): void
{
    foreach ($run['question_ids'] as $id) {
        $question = Question::findOrFail($id);
        actingAs($user)->postJson('/answers', [
            'study_run_id' => $run['id'], 'learning_module_id' => $module->id, 'question_id' => $id,
            'context' => 'lesson', 'answer' => $correct ? correctChoice($question) : incorrectChoice($question),
        ])->assertOk()->assertJson(['correct' => $correct]);
    }
}

test('全問を短い単元へ割り当てても監査済み問題と初見模試のIDは変わらない', function () {
    expect(LearningModule::where('is_active', true)->count())->toBe(57)
        ->and(DB::table('learning_module_question')->count())->toBe(567)
        ->and(DB::table('learning_module_question')->distinct()->count('question_id'))->toBe(567)
        ->and(Question::query()->published()->practiceBank()->count())->toBe(447);
    foreach (LearningModule::all() as $module) {
        expect(Question::query()->published()->practiceBank()->whereKey($module->example_question_id)->exists())->toBeTrue();
    }
    actingAs($this->user)->get('/learn')->assertInertia(fn ($page) => $page
        ->where('curriculum.next.slug', 'payslip')->where('curriculum.module_count', 57)
        ->where('curriculum.passed_count', 0));
    actingAs($this->user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('summary.next_action_href', '/study/payslip'));
});

test('最初の練習は給与明細だけであり定時決定や例外を混ぜない', function () {
    [$module, $run] = openStudy($this->user);
    expect($run['phase'])->toBe('guided')->and($run['question_ids'])->toHaveCount(2);
    expect(Question::whereIn('id', $run['question_ids'])->pluck('source_id')->all())
        ->not->toContain('q-0260', 'q-0271');
    actingAs($this->user)->get('/study/payslip')->assertInertia(fn ($page) => $page
        ->has('study.example')->missing('questions.0.answer')->missing('questions.0.explanation'));
});

test('練習の正解は自力の習熟へ加算せず翌日の復習へ回す', function () {
    [$module, $run] = openStudy($this->user);
    answerStudy($this->user, $module, $run);
    $progress = $this->user->questionProgresses()->where('question_id', $run['question_ids'][0])->firstOrFail();
    expect($progress->correct_count)->toBe(0)->and($progress->state)->toBe('learning');
    expect($this->user->attempts()->where('assisted', true)->count())->toBe(2);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk()->assertJsonPath('study_result.passed', false);
    expect(LearningModuleProgress::first()->independent_passed_at)->toBeNull();
    actingAs($this->user)->get('/study/payslip')->assertInertia(fn ($page) => $page
        ->where('study.phase', 'check')->where('study.example', null)->where('study.memory_tip', null));
});

test('自力確認は未出問題を先に出し80%以上で次の基礎へ進む', function () {
    [$module, $guided] = openStudy($this->user);
    answerStudy($this->user, $module, $guided);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk();
    [$module, $check] = openStudy($this->user);
    $first = $check['question_ids'][0];
    expect(in_array($first, $guided['question_ids'], true))->toBeFalse();
    answerStudy($this->user, $module, $check);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk()
        ->assertJsonPath('study_result.passed', true)->assertJsonPath('study_result.retained', false)
        ->assertJsonPath('study_result.next_href', '/study/pay-cycle');
    expect(LearningModuleProgress::first()->independent_passed_at)->not->toBeNull();
});

test('手助けの利用はサーバーに保存しクライアントの申告で自力正解へ変えられない', function () {
    [$module, $run] = openStudy($this->user, mode: 'check');
    foreach ($run['question_ids'] as $id) {
        actingAs($this->user)->postJson('/study/payslip/support', ['question_id' => $id, 'study_run_id' => $run['id']])->assertOk();
        actingAs($this->user)->postJson('/answers', [
            'study_run_id' => $run['id'], 'learning_module_id' => $module->id, 'question_id' => $id, 'context' => 'lesson',
            'answer' => correctChoice(Question::findOrFail($id)), 'assisted' => false,
        ])->assertOk()->assertJsonPath('assisted', true);
    }
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk()
        ->assertJsonPath('study_result.passed', false)->assertJsonPath('study_result.independent_accuracy', 0);
    expect(LearningModuleProgress::first()->needs_support)->toBeTrue();
});

test('ヒントで表示した例と同じ問題も自力正解へ含めない', function () {
    [$module, $run] = openStudy($this->user, mode: 'check');
    actingAs($this->user)->postJson('/study/payslip/support', ['question_id' => $run['question_ids'][0], 'study_run_id' => $run['id']])->assertOk();
    expect(session("study_runs.{$module->id}.hinted_ids"))->toContain($module->example_question_id);
});

test('未回答の完了と配信外の問題と二重解答を拒否する', function () {
    [$module, $run] = openStudy($this->user);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertStatus(422);
    $outside = Question::where('source_id', 'q-0260')->firstOrFail();
    actingAs($this->user)->postJson('/answers', [
        'study_run_id' => $run['id'], 'learning_module_id' => $module->id, 'question_id' => $outside->id, 'context' => 'lesson', 'answer' => correctChoice($outside),
    ])->assertStatus(422);
    answerStudy($this->user, $module, $run);
    $question = Question::findOrFail($run['question_ids'][0]);
    actingAs($this->user)->postJson('/answers', [
        'study_run_id' => $run['id'], 'learning_module_id' => $module->id, 'question_id' => $question->id, 'context' => 'lesson', 'answer' => correctChoice($question),
    ])->assertStatus(422);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk();
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertStatus(422);
    expect(DB::table('study_run_completions')->count())->toBe(1);
});

test('再表示で問題と手助け履歴を保ち解答済み位置を返す', function () {
    [$module, $run] = openStudy($this->user);
    $question = Question::findOrFail($run['question_ids'][0]);
    actingAs($this->user)->postJson('/answers', [
        'study_run_id' => $run['id'], 'learning_module_id' => $module->id, 'question_id' => $question->id, 'context' => 'lesson', 'answer' => correctChoice($question),
    ])->assertOk();
    actingAs($this->user)->get('/study/payslip')->assertInertia(fn ($page) => $page
        ->where('study.answered_ids', [$question->id])->where('study.correct_count', 1));
    expect(session("study_runs.{$module->id}.id"))->toBe($run['id']);
});

test('同日反復は定着に数えず日を空けて自力で解けた時だけ後日確認になる', function () {
    [$module, $run] = openStudy($this->user, mode: 'check');
    answerStudy($this->user, $module, $run);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk();
    [$module, $sameDay] = openStudy($this->user);
    expect($sameDay['phase'])->toBe('check');
    answerStudy($this->user, $module, $sameDay);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk()->assertJsonPath('study_result.retained', false);
    $this->travel(4)->days();
    [$module, $later] = openStudy($this->user);
    expect($later['phase'])->toBe('spaced');
    answerStudy($this->user, $module, $later);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk()->assertJsonPath('study_result.retained', true);
    expect(LearningModuleProgress::first()->spaced_passed_at)->not->toBeNull();
});

test('教材再同期で既存の問題IDと自力確認を維持し改訂された問題だけ再確認へ戻す', function () {
    [$module, $run] = openStudy($this->user, mode: 'check');
    answerStudy($this->user, $module, $run);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk();
    $ids = Question::pluck('id', 'source_id')->all();
    seed(ContentSeeder::class);
    expect(Question::pluck('id', 'source_id')->all())->toBe($ids)
        ->and(LearningModuleProgress::first()->independent_passed_at)->not->toBeNull();
    $question = $module->questions()->firstOrFail();
    $question->update(['content_revision' => $question->content_revision + 1]);
    actingAs($this->user)->get('/learn')->assertInertia(fn ($page) => $page->where('curriculum.passed_count', 0));
});

test('自力確認の失敗後は正解を覚えて再送するより例に戻る', function () {
    [$module, $run] = openStudy($this->user, mode: 'check');
    answerStudy($this->user, $module, $run, false);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk()
        ->assertJsonPath('study_result.passed', false)->assertJsonPath('study_result.next_href', '/study/payslip');
    actingAs($this->user)->get('/study/payslip')->assertInertia(fn ($page) => $page->where('study.phase', 'guided'));
});

test('練習で獲得したXPは初めて自力で解いたXPを奪わない', function () {
    [$module, $run] = openStudy($this->user);
    answerStudy($this->user, $module, $run);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => session('study_runs.'.$module->id.'.id') ?? $run['id']])->assertOk();
    [$module, $check] = openStudy($this->user);
    $repeatedId = collect($check['question_ids'])->intersect($run['question_ids'])->first();
    $question = Question::findOrFail($repeatedId);
    actingAs($this->user)->postJson('/answers', [
        'study_run_id' => $check['id'], 'learning_module_id' => $module->id, 'question_id' => $question->id, 'context' => 'lesson', 'answer' => correctChoice($question),
    ])->assertOk()->assertJsonPath('assisted', false)->assertJsonPath('xp_earned', $question->difficulty->xp());
});

test('別タブで段階を切り替えたとき古い練習画面を自力解答として受け入れない', function () {
    [$module, $old] = openStudy($this->user);
    [$module, $current] = openStudy($this->user, mode: 'check');
    expect($current['phase'])->toBe('check')->and($current['id'])->not->toBe($old['id']);
    $question = Question::findOrFail($old['question_ids'][0]);
    actingAs($this->user)->postJson('/answers', [
        'study_run_id' => $old['id'], 'learning_module_id' => $module->id, 'question_id' => $question->id,
        'context' => 'lesson', 'answer' => correctChoice($question),
    ])->assertStatus(422);
    actingAs($this->user)->postJson('/study/payslip/support', [
        'study_run_id' => $old['id'], 'question_id' => $question->id,
    ])->assertStatus(422);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => $old['id']])->assertStatus(422);
    expect($this->user->attempts()->count())->toBe(0);
});

test('同じ完了要求が残ったセッションから届いても完了と報酬は増えない', function () {
    [$module, $run] = openStudy($this->user);
    answerStudy($this->user, $module, $run);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => $run['id']])->assertOk();
    $xp = $this->user->statOrCreate()->refresh()->total_xp;
    actingAs($this->user)->withSession(["study_runs.{$module->id}" => $run])
        ->postJson('/study/payslip/complete', ['study_run_id' => $run['id']])->assertStatus(422);
    expect(LearningModuleProgress::first()->completed_count)->toBe(1)
        ->and($this->user->statOrCreate()->refresh()->total_xp)->toBe($xp);
});
