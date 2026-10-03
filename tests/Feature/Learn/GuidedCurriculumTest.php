<?php

use App\Enums\QuestionReviewStatus;
use App\Models\Course;
use App\Models\LearningModule;
use App\Models\LearningModuleProgress;
use App\Models\Question;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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
            'context' => 'lesson', 'answer' => $question->type->value === 'numeric'
                ? (string) ($correct ? $question->answer['value'] : $question->answer['value'] + 1)
                : ($correct ? correctChoice($question) : incorrectChoice($question)),
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

test('57単元すべてで公開通常問題を自力確認し最後は初見模試へ進む', function () {
    // Exercise the whole course at machine speed; normal request throttling remains enabled elsewhere.
    $this->withoutMiddleware(ThrottleRequests::class);
    foreach (LearningModule::orderBy('position')->get() as $module) {
        [$module, $run] = openStudy($this->user, $module->slug, 'check');
        expect(Question::query()->practiceBank()->whereIn('id', $run['question_ids'])->count())
            ->toBe(count($run['question_ids']));
        answerStudy($this->user, $module, $run);
        actingAs($this->user)->postJson("/study/{$module->slug}/complete", ['study_run_id' => $run['id']])
            ->assertOk()->assertJsonPath('study_result.passed', true);
    }
    actingAs($this->user)->get('/learn')->assertInertia(fn ($page) => $page
        ->where('curriculum.passed_count', 57)->where('curriculum.next', null));
    actingAs($this->user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('summary.next_action_href', '/mock-exams'));
});

test('80パーセントでも未確認の論点を飛ばさず次の短い回で優先する', function () {
    [$module, $run] = openStudy($this->user, 'commute-tax', 'check');
    $questions = Question::whereIn('id', $run['question_ids'])->get();
    $single = $questions->groupBy('concept_key')->first(fn ($group) => $group->count() === 1)->first();
    foreach ($run['question_ids'] as $id) {
        $question = Question::findOrFail($id);
        actingAs($this->user)->postJson('/answers', [
            'study_run_id' => $run['id'], 'learning_module_id' => $module->id, 'question_id' => $id,
            'context' => 'lesson', 'answer' => $id === $single->id ? incorrectChoice($question) : correctChoice($question),
        ])->assertOk();
    }
    actingAs($this->user)->postJson('/study/commute-tax/complete', ['study_run_id' => $run['id']])->assertOk()
        ->assertJsonPath('study_result.independent_accuracy', 80)
        ->assertJsonPath('study_result.passed', false)->assertJsonPath('study_result.needs_more', true)
        ->assertJsonPath('study_result.remaining_concept_count', 1);
    expect(LearningModuleProgress::first()->independent_passed_at)->toBeNull();
    [$module, $next] = openStudy($this->user, 'commute-tax');
    expect(Question::findOrFail($next['question_ids'][0])->concept_key)->toBe($single->concept_key);
    answerStudy($this->user, $module, $next);
    actingAs($this->user)->postJson('/study/commute-tax/complete', ['study_run_id' => $next['id']])
        ->assertOk()->assertJsonPath('study_result.passed', true);
});

test('期限前の再練習をしても単元の復習期限を先送りしない', function () {
    [$module, $run] = openStudy($this->user, mode: 'check');
    answerStudy($this->user, $module, $run);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => $run['id']])->assertOk();
    $due = LearningModuleProgress::first()->review_due_at->toDateString();
    $this->travel(2)->days();
    [$module, $repeat] = openStudy($this->user);
    answerStudy($this->user, $module, $repeat);
    actingAs($this->user)->postJson('/study/payslip/complete', ['study_run_id' => $repeat['id']])
        ->assertOk()->assertJsonPath('study_result.retained', false)->assertJsonPath('study_result.review_due_at', $due);
});

test('配信外の問題が未承認でも部分教材で達成扱いにしない', function () {
    [$module, $run] = openStudy($this->user, 'resident-tax', 'check');
    answerStudy($this->user, $module, $run);
    actingAs($this->user)->postJson('/study/resident-tax/complete', ['study_run_id' => $run['id']])->assertOk();
    [$module, $repeat] = openStudy($this->user, 'resident-tax');
    $unselected = $module->questions()->whereNotIn('questions.id', $repeat['question_ids'])->firstOrFail();
    $unselected->update(['review_status' => QuestionReviewStatus::InReview]);
    actingAs($this->user)->get('/learn')->assertInertia(fn ($page) => $page
        ->where('curriculum.passed_count', 0)->where('curriculum.unavailable_count', 1));
    actingAs($this->user)->get('/study/resident-tax')->assertStatus(503);
    actingAs($this->user)->postJson('/study/resident-tax/complete', ['study_run_id' => $repeat['id']])->assertStatus(422);
    expect($this->user->attempts()->count())->toBe(count($run['question_ids']));
});

test('全体の手助けを見た後は残りの問題を自力の証拠にしない', function () {
    [$module, $run] = openStudy($this->user, 'commute-tax', 'check');
    $id = $run['question_ids'][0];
    actingAs($this->user)->postJson('/study/commute-tax/support', ['study_run_id' => $run['id'], 'question_id' => $id])->assertOk();
    expect(array_diff($run['question_ids'], session("study_runs.{$module->id}.hinted_ids")))->toBe([]);
    answerStudy($this->user, $module, $run);
    actingAs($this->user)->postJson('/study/commute-tax/complete', ['study_run_id' => $run['id']])->assertOk()
        ->assertJsonPath('study_result.passed', false)->assertJsonPath('study_result.independent_accuracy', 0);
});

test('単元が不正なら同期途中の旧教材更新も履歴もロールバックする', function () {
    $course = Course::where('slug', 'kyuyo-2kyu')->firstOrFail();
    $course->update(['name' => '同期前の名前']);
    $before = Question::pluck('content_hash', 'id')->all();
    $curriculumPath = database_path('seeders/data/learning-curriculum.json');
    File::shouldReceive('json')->andReturnUsing(function (string $path) use ($curriculumPath): array {
        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        if ($path === $curriculumPath) {
            $data['modules'][0]['approach'] = ['', '', ''];
        }

        return $data;
    });
    expect(fn () => seed(ContentSeeder::class))->toThrow(RuntimeException::class);
    expect($course->refresh()->name)->toBe('同期前の名前')
        ->and(Question::pluck('content_hash', 'id')->all())->toBe($before);
});

test('後日確認と再確認でも一度の達成を未確認論点の代わりにしない', function () {
    [$module, $run] = openStudy($this->user, 'commute-tax', 'check');
    answerStudy($this->user, $module, $run);
    actingAs($this->user)->postJson('/study/commute-tax/complete', ['study_run_id' => $run['id']])->assertOk();
    $this->travel(4)->days();
    [$module, $spaced] = openStudy($this->user, 'commute-tax');
    expect($spaced['phase'])->toBe('spaced');
    $questions = Question::whereIn('id', $spaced['question_ids'])->get();
    $single = $questions->groupBy('concept_key')->first(fn ($group) => $group->count() === 1)->first();
    foreach ($spaced['question_ids'] as $id) {
        $q = Question::findOrFail($id);
        actingAs($this->user)->postJson('/answers', [
            'study_run_id' => $spaced['id'], 'learning_module_id' => $module->id, 'question_id' => $id,
            'context' => 'lesson', 'answer' => $id === $single->id ? incorrectChoice($q) : correctChoice($q),
        ])->assertOk();
    }
    actingAs($this->user)->postJson('/study/commute-tax/complete', ['study_run_id' => $spaced['id']])->assertOk()
        ->assertJsonPath('study_result.retained', false)->assertJsonPath('study_result.needs_more', true);
    [$module, $remaining] = openStudy($this->user, 'commute-tax');
    expect($remaining['phase'])->toBe('spaced')
        ->and(Question::findOrFail($remaining['question_ids'][0])->concept_key)->toBe($single->concept_key);
    answerStudy($this->user, $module, $remaining);
    actingAs($this->user)->postJson('/study/commute-tax/complete', ['study_run_id' => $remaining['id']])->assertOk()
        ->assertJsonPath('study_result.retained', true);
    [$module, $again] = openStudy($this->user, 'commute-tax', 'check');
    $q = Question::findOrFail($again['question_ids'][0]);
    // A whole-guide hint invalidates the remaining independent evidence on a recheck.
    actingAs($this->user)->postJson('/study/commute-tax/support', ['study_run_id' => $again['id'], 'question_id' => $q->id])->assertOk();
    answerStudy($this->user, $module, $again);
    actingAs($this->user)->postJson('/study/commute-tax/complete', ['study_run_id' => $again['id']])->assertOk()
        ->assertJsonPath('study_result.passed', false);
    expect(LearningModuleProgress::first()->independent_passed_at)->toBeNull();
});

test('同期前の空の単元一覧を学習完了や模試準備完了として扱わない', function () {
    LearningModule::query()->delete();
    actingAs($this->user)->get('/learn')->assertInertia(fn ($page) => $page
        ->where('curriculum.module_count', 0)->where('curriculum.next', null));
    actingAs($this->user)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('summary.next_action_href', '/learn')->where('summary.next_action_label', '教材の公開状況を見る'));
});
