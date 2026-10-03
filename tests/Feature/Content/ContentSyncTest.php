<?php

use App\Console\Commands\SyncQuestionContent;
use App\Models\Course;
use App\Models\Question;
use App\Models\ReferenceSheet;
use App\Services\QuestionReviewLedger;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\ReviewLedgerFixture;

use function Pest\Laravel\artisan;

test('監査未完了のシードはコースや問題を変更する前に停止する', function () {
    app()->instance(QuestionReviewLedger::class, new QuestionReviewLedger([]));
    expect(fn () => $this->seed(ContentSeeder::class))->toThrow(RuntimeException::class);
    expect(Course::count())->toBe(0)
        ->and(Question::count())->toBe(0)
        ->and(ReferenceSheet::count())->toBe(0);
});

test('同じ正本リリースではDB上のレビュー結果を上書きしない', function () {
    artisan('content:sync')->assertSuccessful();

    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $question->update(['review_notes' => '管理画面で行った再レビュー']);

    artisan('content:sync')
        ->expectsOutput('問題コンテンツは最新です。DB上のレビュー結果を保持します。')
        ->assertSuccessful();

    expect($question->refresh()->review_notes)->toBe('管理画面で行った再レビュー');
});

test('同じリリースの再確認日は通知しDB上のレビューを保持して同期成功とする', function () {
    artisan('content:sync')->assertSuccessful();
    $hash = DB::table('content_releases')->value('bundle_hash');
    $question = Question::where('source_id', 'q-0001')->firstOrFail();
    $question->update(['review_notes' => '既存の確認記録']);
    $records = ReviewLedgerFixture::records();
    $records['q-0001']['reviewed_at'] = today()->subDays(2)->toDateString();
    $records['q-0001']['review_due_at'] = today()->subDay()->toDateString();
    app()->instance(QuestionReviewLedger::class, new QuestionReviewLedger($records));
    artisan('content:sync')
        ->expectsOutput('教材の再確認待ち1件。承認済みの内容を保持して同期します。')
        ->assertSuccessful();
    expect($question->refresh()->review_notes)->toBe('既存の確認記録');
    expect(DB::table('content_releases')->value('bundle_hash'))->toBe($hash)
        ->and(Question::count())->toBe(567);
});

test('同じリリースでも未承認・内容不一致・不正な再確認日を同期成功として扱わない', function () {
    artisan('content:sync')->assertSuccessful();
    foreach (['verification', 'fingerprint', 'review_due_at'] as $field) {
        $records = ReviewLedgerFixture::records();
        $records['q-0001'][$field] = 'invalid';
        app()->instance(QuestionReviewLedger::class, new QuestionReviewLedger($records));
        expect(fn () => app(SyncQuestionContent::class)->handle())->toThrow(RuntimeException::class);
        expect(Question::count())->toBe(567);
    }
});
