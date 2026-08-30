<?php

use App\Models\Question;

use function Pest\Laravel\artisan;

test('同じ正本リリースではDB上のレビュー結果を上書きしない', function () {
    artisan('content:sync')->assertSuccessful();

    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $question->update(['review_notes' => '管理画面で行った再レビュー']);

    artisan('content:sync')
        ->expectsOutput('問題コンテンツは最新です。DB上のレビュー結果を保持します。')
        ->assertSuccessful();

    expect($question->refresh()->review_notes)->toBe('管理画面で行った再レビュー');
});
