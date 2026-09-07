<?php

use App\Enums\QuestionType;
use App\Models\MockExam;
use App\Models\Question;
use App\Services\CalcVerifier;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\File;

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

test('監査で独立計算した結果と正答選択肢・検算器が一致する', function () {
    // Reviewed arithmetic from the question statements, not generated from calc_params.
    $expected = [
        'q-0160' => 1563, 'q-0227' => 1600, 'q-0228' => 15000,
        'q-0229' => 1506, 'q-0233' => 3000, 'q-0246' => 2187,
        'q-0675' => 4710, 'q-0676' => 6320, 'q-0677' => 26164,
        'q-0678' => 18813, 'q-0679' => 42570, 'q-0694' => 230160,
        'q-0701' => 220000, 'q-0734' => 10080, 'q-0749' => 1620,
        'q-0827' => 8710, 'q-0828' => 10352, 'q-0829' => 251220,
        'q-0830' => 255080, 'q-0831' => 45000, 'q-0836' => 4300,
    ];
    foreach ($expected as $id => $amount) {
        $question = Question::where('source_id', $id)->firstOrFail();
        expect($question->answer['value'])->toBe($amount)
            ->and(app(CalcVerifier::class)->compute($question))->toBe($amount);
        if ($question->type === QuestionType::Choice) {
            $text = collect($question->choices)->firstWhere('key', $question->answer['choice'])['text'];
            expect($text)->toBe(number_format($amount).'円');
        }
    }
});

test('月額表は上下の行と扶養人数を取り違えず境界で切り替わる', function () {
    $question = Question::where('source_id', 'q-0675')->firstOrFail();
    foreach ([[253999, 1, 4590], [254000, 1, 4710], [256999, 2, 3090], [257000, 2, 3200]] as [$amount, $dependents, $expected]) {
        $question->calc_params = ['calc_type' => 'withholding_tax_monthly', 'gross' => $amount, 'social_insurance' => 0,
            'dependents' => $dependents, 'table' => 'gensen-getsugaku'];
        expect(app(CalcVerifier::class)->compute($question))->toBe($expected);
    }
});

test('欠勤控除は割増単価を丸めず規程どおり最終額だけ切り捨てる', function () {
    $question = Question::where('source_id', 'q-0246')->firstOrFail();
    // 160 hours = 9,600 minutes. Fixed independent results include a fractional unit rate.
    foreach ([[200000, 105, 2187], [200080, 105, 2188], [200080, 60, 1250], [200000, 0, 0], [200000, 96, 2000]] as [$wage, $minutes, $expected]) {
        $question->calc_params = ['calc_type' => 'absence_deduction_floor', 'monthly_wage' => $wage,
            'monthly_minutes' => 9600, 'absence_minutes' => $minutes];
        expect(app(CalcVerifier::class)->compute($question))->toBe($expected);
    }
});

test('月合計の30分境界と単価・保険料の50銭境界を区別する', function () {
    $question = Question::where('source_id', 'q-0228')->firstOrFail();
    foreach ([[629, 15000], [630, 16500]] as [$minutes, $expected]) {
        $params = $question->calc_params;
        $params['total_minutes'] = $minutes;
        $question->calc_params = $params;
        expect(app(CalcVerifier::class)->compute($question))->toBe($expected);
    }
    expect(CalcVerifier::roundHalfUp(100.50))->toBe(101)
        ->and(CalcVerifier::roundHalfDown(100.50))->toBe(100)
        ->and(CalcVerifier::roundHalfDown(100.51))->toBe(101);
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

test('本文から追加抽出した金額問題の正答を登録式に依存しない期待値で照合する', function () {
    $cases = File::json(base_path('tests/Fixtures/calculation-review-expectations.json'));
    $reviews = File::json(database_path('seeders/data/question-reviews.json'))['questions'];
    foreach ($cases as $id => $case) {
        if (($case['status'] ?? 'active') === 'retired') {
            expect($reviews[$id]['verification'])->toBe('retired')
                ->and($reviews[$id]['merged_into'])->toBe($case['merged_into']);
            $this->assertDatabaseMissing('questions', ['source_id' => $id]);
            $question = $reviews[$id]['retired_question'];
            $chosen = collect($question['choices'])->firstWhere('key', $question['answer']['choice'])['text'];
        } else {
            $question = Question::where('source_id', $id)->firstOrFail();
            $chosen = collect($question->choices)->firstWhere('key', $question->answer['choice'])['text'];
        }
        expect(preg_match('/([0-9,]+)(万)?円/u', $chosen, $matches))->toBe(1, $id);
        $amount = (int) str_replace(',', '', $matches[1]) * (($matches[2] ?? '') === '万' ? 10000 : 1);
        expect($amount)->toBe($case['expected_yen'], $id.': '.$case['reason']);
    }
});
