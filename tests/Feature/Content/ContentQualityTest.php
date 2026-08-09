<?php

use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\Unit;
use App\Services\ContentAuditService;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\File;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(ContentSeeder::class);
});

test('公開対象は45学習目標それぞれに意味の異なる2変種を持つ90問だけ', function () {
    $questions = Question::query()->published()->get();

    expect($questions)->toHaveCount(90)
        ->and($questions->groupBy('concept_key'))->toHaveCount(45)
        ->and($questions->groupBy('concept_key')->every(fn ($variants): bool => $variants->count() === 2))->toBeTrue()
        ->and($questions->groupBy('concept_key')->every(
            fn ($variants): bool => $variants->pluck('variant_role')->unique()->count() === 2,
        ))->toBeTrue()
        ->and(Question::where('source_id', 'like', 'gen-%')->where('is_active', true)->count())->toBe(0)
        ->and(Question::query()->published()->whereNull('concept_key')->count())->toBe(0)
        ->and(Question::query()->published()->whereNull('learning_objective')->count())->toBe(0)
        ->and(Question::query()->published()->whereNull('variant_role')->count())->toBe(0)
        ->and(Unit::where('slug', 'nencho')->exists())->toBeFalse()
        ->and(Question::query()->published()->whereNull('reviewed_content_hash')->count())->toBe(0);
});

test('公開コンテンツ監査にエラーがない', function () {
    $result = app(ContentAuditService::class)->audit();

    expect($result['errors'])->toBe([])
        ->and($result['stats']['published_questions'])->toBe(90)
        ->and($result['stats']['learning_objectives'])->toBe(45)
        ->and($result['stats']['published_mock_exams'])->toBe(1);
});

test('公開模試は公式公開仕様の40問100点かつ全問四肢択一', function () {
    $exam = MockExam::where('is_published', true)->sole();
    $items = $exam->examQuestions()->with('question')->get();

    expect($items)->toHaveCount(40)
        ->and($items->sum('points'))->toBe(100)
        ->and($items->take(35)->every(fn ($item): bool => $item->points === 2 && ! $item->question->isCalculation()))->toBeTrue()
        ->and($items->skip(35)->every(fn ($item): bool => $item->points === 6 && $item->question->isCalculation()))->toBeTrue()
        ->and($items->every(fn ($item): bool => $item->question->type === QuestionType::Choice))->toBeTrue()
        ->and($items->every(fn ($item): bool => count($item->question->choices) === 4))->toBeTrue()
        ->and($items->every(fn ($item): bool => $item->question->review_status === QuestionReviewStatus::Approved))->toBeTrue();
});

test('正解位置は問題バンク全体と模試で偏らず模試は応用系を中心に構成する', function () {
    $questions = Question::query()->published()->where('type', QuestionType::Choice)->get();
    $bankCounts = $questions->countBy(fn (Question $question): string => $question->answer['choice']);
    $examQuestions = MockExam::where('is_published', true)->sole()
        ->examQuestions()->with('question')->get()->pluck('question');
    $examCounts = $examQuestions->countBy(fn (Question $question): string => $question->answer['choice']);
    $knowledgeRoles = $examQuestions->take(35)->countBy(fn (Question $question): string => $question->variant_role->value);

    expect($bankCounts->sortKeys()->all())->toBe(['A' => 22, 'B' => 24, 'C' => 21, 'D' => 22])
        ->and($examCounts->sortKeys()->all())->toBe(['A' => 10, 'B' => 10, 'C' => 10, 'D' => 10])
        ->and($knowledgeRoles['recall'])->toBeLessThanOrEqual(7)
        ->and($knowledgeRoles->except('recall')->sum())->toBeGreaterThanOrEqual(25);
});

test('選択肢再配置後も正答と誤答別フィードバックの対応を維持する', function () {
    foreach (File::files(database_path('seeders/data/questions')) as $file) {
        foreach (File::json($file->getPathname()) as $source) {
            if ($source['type'] !== QuestionType::Choice->value) {
                continue;
            }

            $question = Question::where('source_id', $source['source_id'])->firstOrFail();
            $originalChoices = collect($source['choices'])->pluck('text', 'key');
            $displayedChoices = collect($question->choices)->pluck('text', 'key');
            $correctText = $originalChoices[$source['answer']['choice']];

            expect($displayedChoices[$question->answer['choice']])->toBe($correctText);

            foreach ($source['distractor_feedback'] ?? [] as $originalKey => $feedback) {
                $displayedKey = $displayedChoices->search($originalChoices[$originalKey], strict: true);

                expect($displayedKey)->not->toBeFalse()
                    ->and($question->distractor_feedback[$displayedKey])->toBe($feedback);
            }
        }
    }
});

test('問題内容ハッシュは正解と解説の変更も検知する', function () {
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();
    $content = $question->only([
        'type', 'question_text', 'choices', 'answer', 'explanation', 'common_mistake', 'distractor_feedback', 'calc_params',
    ]);

    expect(Question::contentHash($content))->toBe($question->content_hash);

    $content['explanation'] .= '変更';

    expect(Question::contentHash($content))->not->toBe($question->content_hash);
});

test('レビュー期限切れの問題はレッスンと新規模試から自動的に除外される', function () {
    $question = Question::where('source_id', 'r2-q01')->firstOrFail();
    $question->update(['review_due_at' => now()->subMinute()]);

    expect(Question::query()->published()->whereKey($question->id)->exists())->toBeFalse()
        ->and(MockExam::where('slug', 'mogi-1')->firstOrFail()->isAvailableForNewAttempt())->toBeFalse()
        ->and(app(ContentAuditService::class)->audit()['errors'])
        ->toContain("r2-q01: レビュー期限（{$question->review_due_at->toDateString()}）を過ぎています。");
});

test('監査は計算問題の登録正答と再計算値の不一致を検出する', function () {
    $question = Question::where('source_id', 'r2-q36')->firstOrFail();
    $answer = $question->answer;
    $answer['value']++;
    $question->answer = $answer;
    $hash = Question::contentHash($question->only([
        'type', 'question_text', 'choices', 'answer', 'explanation', 'common_mistake', 'distractor_feedback', 'calc_params',
    ]));
    $question->content_hash = $hash;
    $question->reviewed_content_hash = $hash;
    $question->save();

    expect(app(ContentAuditService::class)->audit()['errors'])
        ->toContain('r2-q36: 計算式の再計算値26164円が登録正答と一致しません。');
});
