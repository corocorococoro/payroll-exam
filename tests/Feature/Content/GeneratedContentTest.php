<?php

use App\Models\MockExam;
use App\Models\Question;
use App\Models\Unit;
use App\Services\CalcVerifier;
use Database\Seeders\ContentSeeder;
use Database\Seeders\GeneratedContentSeeder;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed([ContentSeeder::class, GeneratedContentSeeder::class]);
});

test('分野別目標どおり300問を生成する', function () {
    expect(Question::where('source_id', 'like', 'gen-%')->count())->toBe(300);

    foreach (GeneratedContentSeeder::TARGET_COUNTS as $slug => $count) {
        $unit = Unit::where('slug', $slug)->firstOrFail();
        expect(Question::where('unit_id', $unit->id)->where('source_id', 'like', 'gen-%')->count())
            ->toBe($count);
    }
});

test('レポート48問と生成300問で合計348問になる', function () {
    expect(Question::where('source_id', 'like', 'r2-q%')->count())->toBe(48)
        ->and(Question::where('source_id', 'like', 'gen-%')->count())->toBe(300)
        ->and(Question::count())->toBe(348);
});

test('生成問題は解説と典型ミスを持ち、正解形式が妥当', function () {
    Question::where('source_id', 'like', 'gen-%')->each(function (Question $question): void {
        expect($question->explanation)->not->toBeEmpty()
            ->and($question->common_mistake)->not->toBeEmpty();

        if ($question->type->value === 'choice') {
            expect(collect($question->choices)->pluck('key'))->toContain($question->answer['choice']);
        }
    });
});

test('知識問題は番号だけを変えた重複文を含まない', function () {
    $knowledge = Question::where('source_id', 'like', 'gen-%')
        ->where('type', 'choice')
        ->pluck('question_text');

    expect($knowledge)->toHaveCount(260)
        ->and($knowledge->unique())->toHaveCount(260)
        ->and($knowledge->contains(fn (string $text) => str_contains($text, '演習バリエーション')))->toBeFalse();
});

test('生成した全計算問題をcalc_paramsから再計算できる', function () {
    $verifier = app(CalcVerifier::class);
    $questions = Question::where('source_id', 'like', 'gen-keisan-%')->get();

    expect($questions)->toHaveCount(40);
    foreach ($questions as $question) {
        expect($verifier->compute($question))->toBe((int) $question->answer['value']);
    }
});

test('追加模試2セットも40問100点で構成される', function () {
    foreach (['mogi-2', 'mogi-3'] as $slug) {
        $exam = MockExam::where('slug', $slug)->firstOrFail();
        expect($exam->examQuestions()->count())->toBe(40)
            ->and($exam->examQuestions()->sum('points'))->toBe(100);
    }
});
