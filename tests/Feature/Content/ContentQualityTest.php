<?php

use App\Enums\QuestionReviewStatus;
use App\Enums\QuestionType;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\Unit;
use App\Services\ContentAuditService;
use App\Services\OfficialSourceService;
use Database\Seeders\ContentSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

use function Pest\Laravel\seed;

beforeEach(function () {
    seed(ContentSeeder::class);
});

test('単一問題バンクの全問が同じ公開品質基準を満たす', function () {
    $bank = File::json(database_path('seeders/data/question-bank.json'));
    $questions = Question::query()->published()->get();
    $choiceQuestions = $questions->where('type', QuestionType::Choice);
    $numericQuestions = $questions->where('type', QuestionType::Numeric);

    expect($questions)->toHaveCount($bank['release']['question_count'])
        ->and($choiceQuestions)->toHaveCount($bank['release']['question_count'] - 1)
        ->and($numericQuestions)->toHaveCount(1)
        ->and(Schema::hasColumn('questions', 'source_collection'))->toBeFalse()
        ->and(Schema::hasColumn('questions', 'source_question_number'))->toBeFalse()
        ->and(Schema::hasColumn('questions', 'source_chapter'))->toBeFalse()
        ->and(Schema::hasColumn('questions', 'source_chapter_title'))->toBeFalse()
        ->and(Schema::hasColumn('questions', 'source_page'))->toBeFalse()
        ->and($questions->every(fn (Question $question): bool => trim($question->explanation) !== ''))->toBeTrue()
        ->and($choiceQuestions->every(fn (Question $question): bool => count($question->choices) === 4
            && collect($question->choices)->contains('key', $question->answer['choice'])))->toBeTrue()
        ->and($questions->every(
            fn (Question $question): bool => $question->verification_status === 'official_sources_reviewed'
                && $question->scope_status === 'exam_2026-09-01'
                && $question->source_urls !== []
                && collect($question->source_urls)->every(
                    fn (string $url): bool => app(OfficialSourceService::class)->isOfficialUrl($url),
                ),
        ))->toBeTrue()
        ->and($questions->groupBy('concept_key')->every(
            fn ($variants): bool => $variants->pluck('learning_objective')->unique()->count() === 1
                && $variants->where('study_tier', 'core')->isNotEmpty(),
        ))->toBeTrue()
        ->and($questions->where('study_tier', 'core'))->toHaveCount($bank['release']['core_question_count'])
        ->and($questions->pluck('study_tier')->unique()->sort()->values()->all())
        ->toBe(['core', 'reinforcement'])
        ->and(Question::where('source_id', 'like', 'gen-%')->where('is_active', true)->count())->toBe(0)
        ->and(Question::query()->published()->whereNull('concept_key')->count())->toBe(0)
        ->and(Question::query()->published()->whereNull('learning_objective')->count())->toBe(0)
        ->and(Question::query()->published()->whereNull('variant_role')->count())->toBe(0)
        ->and(Unit::where('slug', 'nencho')->exists())->toBeFalse()
        ->and(Question::query()->published()->whereNull('reviewed_content_hash')->count())->toBe(0);
});

test('公式試験案内と矛盾した年末調整2問は正しい2級範囲へ補正される', function () {
    $scopeQuestion = Question::where('source_id', 'q-0348')->firstOrFail();
    $dateQuestion = Question::where('source_id', 'q-0396')->firstOrFail();

    $scopeAnswer = collect($scopeQuestion->choices)->firstWhere('key', $scopeQuestion->answer['choice'])['text'];
    $dateAnswer = collect($dateQuestion->choices)->firstWhere('key', $dateQuestion->answer['choice'])['text'];

    expect($scopeAnswer)->toBe('年末調整を除く通常の月次給与・賞与計算を扱う')
        ->and($scopeQuestion->explanation)->toContain('年末調整を含む総合的な実務は1級')
        ->and($dateAnswer)->toBe('試験実施月の前々月の1日')
        ->and($dateQuestion->explanation)->toContain('11月の2級試験は同年9月1日現在');
});

test('解答画面用の根拠資料は公式httpsだけを返す', function () {
    $commutingSources = app(OfficialSourceService::class)->forQuestion(
        Question::where('source_id', 'q-0411')->firstOrFail(),
    );
    $continuationSources = app(OfficialSourceService::class)->forQuestion(
        Question::where('source_id', 'q-0610')->firstOrFail(),
    );
    $childcareSources = app(OfficialSourceService::class)->forQuestion(
        Question::where('source_id', 'q-0791')->firstOrFail(),
    );
    $sources = collect([$commutingSources, $continuationSources, $childcareSources])->flatten(1);

    expect($sources)->not->toBeEmpty()
        ->and($sources->every(
            fn (array $source): bool => str_starts_with($source['url'], 'https://'),
        ))->toBeTrue()
        ->and($sources->pluck('label'))
        ->toContain('国税庁：2026年の通勤手当')
        ->toContain('協会けんぽ：退職後の健康保険')
        ->toContain('日本年金機構：産休・育休中の保険料');
});

test('各論点には内容の対応した公式資料だけが表示される', function () {
    $expectations = [
        'q-0260' => '日本年金機構：標準報酬月額・定時決定',
        'q-0302' => '厚生労働省：賃金のデジタル払い',
        'q-0307' => '厚生労働省：最低賃金制度',
        'q-0340' => '鹿児島労働局：割増賃金の端数処理',
        'q-0191' => '厚生労働省：時間外労働の上限と割増賃金',
        'q-0572' => '厚生労働省：介護保険の被保険者',
        'q-0580' => '協会けんぽ：健康保険給付',
        'q-0641' => '日本年金機構：随時改定',
        'q-0684' => '厚生労働省：労働保険の年度更新',
        'q-0414' => '東京都主税局：住民税の特別徴収',
        'q-0411' => '国税庁：2026年の通勤手当',
        'q-0809' => '日本年金機構：賞与の保険料',
        'q-0003' => '厚生労働省：2024年改正の労働条件明示',
        'q-0012' => 'e-Gov法令検索：労働契約法',
        'q-0052' => '厚生労働省：育児・介護休業法',
        'q-0559' => '厚生労働省：労災保険制度',
        'q-0563' => '個人情報保護委員会：マイナンバー取扱指針',
    ];

    foreach ($expectations as $sourceId => $expectedLabel) {
        $sources = app(OfficialSourceService::class)->forQuestion(
            Question::where('source_id', $sourceId)->firstOrFail(),
        );

        expect(collect($sources)->pluck('label'))->toContain($expectedLabel);
    }

    $contractSources = app(OfficialSourceService::class)->forQuestion(
        Question::where('source_id', 'q-0012')->firstOrFail(),
    );

    expect(collect($contractSources)->pluck('label'))
        ->not->toContain('厚生労働省：育児・介護休業法')
        ->not->toContain('日本年金機構：制度・手続の解説');
});

test('全問の公式資料は論点名を持ち汎用ラベルへフォールバックしない', function () {
    $genericLabels = [
        '公式資料',
        '厚生労働省：制度の公式資料',
        '協会けんぽ：健康保険の公式資料',
        '日本年金機構：制度・手続の解説',
        '国税庁：税務の公式資料',
    ];

    $sources = Question::query()->published()->get()->flatMap(
        fn (Question $question): array => app(OfficialSourceService::class)->forQuestion($question),
    );

    expect($sources)->not->toBeEmpty()
        ->and($sources->pluck('label')->intersect($genericLabels))->toBeEmpty();
});

test('公開コンテンツ監査にエラーがない', function () {
    $bank = File::json(database_path('seeders/data/question-bank.json'));
    $result = app(ContentAuditService::class)->audit();

    expect($result['errors'])->toBe([])
        ->and($result['stats']['published_questions'])->toBe($bank['release']['question_count'])
        ->and($result['stats']['learning_objectives'])->toBe(count($bank['topics']))
        ->and($result['stats']['core_questions'])->toBe($bank['release']['core_question_count'])
        ->and($result['stats']['published_mock_exams'])->toBe(3);
});

test('公開模試は公式公開仕様の40問100点かつ全問四肢択一', function () {
    expect(MockExam::where('is_published', true)->count())->toBe(3);

    foreach (MockExam::where('is_published', true)->get() as $exam) {
        $items = $exam->examQuestions()->with('question')->get();

        expect($items)->toHaveCount(40)
            ->and($items->sum('points'))->toBe(100)
            ->and($items->take(35)->every(fn ($item): bool => $item->points === 2 && ! $item->question->isCalculation()))->toBeTrue()
            ->and($items->skip(35)->every(fn ($item): bool => $item->points === 6 && $item->question->isCalculation()))->toBeTrue()
            ->and($items->every(fn ($item): bool => $item->question->type === QuestionType::Choice))->toBeTrue()
            ->and($items->every(fn ($item): bool => count($item->question->choices) === 4))->toBeTrue()
            ->and($items->every(fn ($item): bool => $item->question->review_status === QuestionReviewStatus::Approved))->toBeTrue();
    }
});

test('正解位置は問題バンク全体と模試で偏らない', function () {
    $questions = Question::query()->published()->where('type', QuestionType::Choice)->get();
    $bankCounts = $questions->countBy(fn (Question $question): string => $question->answer['choice']);

    expect($bankCounts)->toHaveKeys(['A', 'B', 'C', 'D'])
        ->and($bankCounts->max() - $bankCounts->min())->toBeLessThanOrEqual(10);

    foreach (MockExam::where('is_published', true)->get() as $exam) {
        $examQuestions = $exam->examQuestions()->with('question')->get()->pluck('question');
        $examCounts = $examQuestions->countBy(fn (Question $question): string => $question->answer['choice']);
        expect($examCounts->max() - $examCounts->min())->toBeLessThanOrEqual(2);
    }
});

test('選択肢再配置後も正答と誤答別フィードバックの対応を維持する', function () {
    $bank = File::json(database_path('seeders/data/question-bank.json'));

    foreach ($bank['questions'] as $source) {
        if ($source['type'] !== QuestionType::Choice->value) {
            continue;
        }

        $question = Question::where('source_id', $source['id'])->firstOrFail();
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
});

test('問題内容ハッシュは正解と解説の変更も検知する', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $content = $question->only([
        'type', 'question_text', 'choices', 'answer', 'explanation', 'common_mistake', 'distractor_feedback', 'calc_params',
    ]);

    expect(Question::contentHash($content))->toBe($question->content_hash);

    $content['explanation'] .= '変更';

    expect(Question::contentHash($content))->not->toBe($question->content_hash);
});

test('レビュー期限切れの問題はレッスンと新規模試から自動的に除外される', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $question->update(['review_due_at' => now()->subMinute()]);

    expect(Question::query()->published()->whereKey($question->id)->exists())->toBeFalse()
        ->and(MockExam::where('slug', 'mogi-1')->firstOrFail()->isAvailableForNewAttempt())->toBeFalse()
        ->and(app(ContentAuditService::class)->audit()['errors'])
        ->toContain("q-0032: レビュー期限（{$question->review_due_at->toDateString()}）を過ぎています。");
});

test('監査は計算問題の登録正答と再計算値の不一致を検出する', function () {
    $question = Question::where('source_id', 'q-0677')->firstOrFail();
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
        ->toContain('q-0677: 計算式の再計算値26164円が登録正答と一致しません。');
});
