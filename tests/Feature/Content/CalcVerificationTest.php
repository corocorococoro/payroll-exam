<?php

use App\Enums\QuestionType;
use App\Models\MockExam;
use App\Models\Question;
use App\Services\CalcVerifier;
use Database\Seeders\ContentSeeder;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(ContentSeeder::class);
});

test('全計算問題の正答が calc_params から再計算した値と一致する', function () {
    $verifier = new CalcVerifier;
    $questions = Question::whereNotNull('calc_params')->get();

    expect($questions)->not->toBeEmpty();

    foreach ($questions as $question) {
        $expected = $question->answer['value'] ?? null;

        expect($expected)->not->toBeNull("計算問題 {$question->source_id} に answer.value がありません");
        expect($verifier->compute($question))->toBe(
            (int) $expected,
            "計算問題 {$question->source_id} の再計算値が正答と一致しません",
        );
    }
});

test('択一問題は正解の選択肢キーが存在し解説を持つ', function () {
    $questions = Question::where('type', QuestionType::Choice)->get();

    expect($questions)->not->toBeEmpty();

    foreach ($questions as $question) {
        $keys = array_column($question->choices, 'key');

        expect(in_array($question->answer['choice'], $keys, true))
            ->toBeTrue("問題 {$question->source_id} の正解キーが選択肢にありません");
        expect($question->explanation)->not->toBe('');
    }
});

test('模試第1回は40問・100点満点で構成されている', function () {
    $exam = MockExam::where('slug', 'mogi-1')->firstOrFail();
    $examQuestions = $exam->examQuestions;

    expect($examQuestions)->toHaveCount(40)
        ->and($examQuestions->sum('points'))->toBe(100)
        ->and($examQuestions->where('points', 2))->toHaveCount(35)
        ->and($examQuestions->where('points', 6))->toHaveCount(5);
});
