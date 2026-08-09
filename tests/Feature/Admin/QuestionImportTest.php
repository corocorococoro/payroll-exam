<?php

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
        'type' => 'choice',
        'category' => '労働法・勤怠',
        'difficulty' => 'easy',
        'fiscal_year' => 2026,
        'question_text' => 'インポート確認問題',
        'choices' => [['key' => 'A', 'text' => '正しい'], ['key' => 'B', 'text' => '誤り']],
        'answer' => ['choice' => 'A'],
        'explanation' => '検証用解説',
        'common_mistake' => '検証用ミス',
    ]], JSON_THROW_ON_ERROR));

    $count = app(QuestionImportService::class)->import($path, 'json');

    expect($count)->toBe(1)
        ->and(Question::where('source_id', 'import-test-001')->value('question_text'))->toBe('インポート確認問題');
});
