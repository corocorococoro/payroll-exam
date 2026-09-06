<?php

use App\Models\Course;
use App\Models\Question;
use App\Models\ReferenceSheet;
use App\Services\QuestionReviewLedger;
use Database\Seeders\ContentSeeder;

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
