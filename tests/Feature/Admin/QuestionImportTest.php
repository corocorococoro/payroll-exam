<?php

use App\Enums\QuestionReviewStatus;
use App\Models\Question;
use App\Services\QuestionImportService;
use Database\Seeders\ContentSeeder;

use function Pest\Laravel\seed;

test('JSONから問題を検証付きでインポートできる', function () {
    seed(ContentSeeder::class);
    $path = tempnam(sys_get_temp_dir(), 'questions-');
    file_put_contents($path, json_encode([[
        'unit' => 'roudou',
        'lesson' => 'chingin-shiharai',
        'source_id' => 'import-test-001',
        'concept_key' => 'import-test-concept',
        'learning_objective' => '問題インポートの正誤を判定できる',
        'variant_role' => 'recall',
        'misconception_key' => 'import-test-mistake',
        'type' => 'choice',
        'category' => '労働法・勤怠',
        'difficulty' => 'easy',
        'fiscal_year' => 2026,
        'question_text' => 'インポート確認問題',
        'choices' => [['key' => 'A', 'text' => '正しい'], ['key' => 'B', 'text' => '誤り']],
        'answer' => ['choice' => 'A'],
        'explanation' => '検証用解説',
        'common_mistake' => '検証用ミス',
        'distractor_feedback' => ['B' => 'Aが正答です。'],
        'source_urls' => ['https://example.com/source'],
    ]], JSON_THROW_ON_ERROR));

    $count = app(QuestionImportService::class)->import($path, 'json');

    expect($count)->toBe(1)
        ->and(Question::where('source_id', 'import-test-001')->value('question_text'))->toBe('インポート確認問題')
        ->and(Question::where('source_id', 'import-test-001')->firstOrFail()->review_status)->toBe(QuestionReviewStatus::Draft)
        ->and(Question::where('source_id', 'import-test-001')->value('is_active'))->toBeFalse();
});
