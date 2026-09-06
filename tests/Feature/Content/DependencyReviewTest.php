<?php

use App\Enums\QuestionReviewStatus;
use App\Filament\Resources\Questions\Pages\EditQuestion;
use App\Models\MockExam;
use App\Models\Question;
use App\Models\ReferenceSheet;
use App\Models\User;
use App\Services\ContentAuditService;
use App\Services\QuestionImportService;
use Database\Seeders\ContentSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(ContentSeeder::class);
});

test('本文が同じでも取り込みの年度・目標・資料変更は旧承認を外す', function (string $field, mixed $value) {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $row = array_replace($question->toArray(), ['unit' => $question->unit->slug, 'lesson' => $question->lesson->slug, $field => $value]);
    $path = tempnam(sys_get_temp_dir(), 'dependency-');
    try {
        file_put_contents($path, json_encode([$row], JSON_THROW_ON_ERROR));
        app(QuestionImportService::class)->import($path, 'json');
    } finally {
        unlink($path);
    }
    expect($question->refresh()->review_status)->toBe(QuestionReviewStatus::InReview)
        ->and($question->content_revision)->toBe(2)
        ->and($question->review_fingerprint)->toBeNull()
        ->and(Question::published()->whereKey($question->id)->exists())->toBeFalse();
})->with([
    ['fiscal_year', 2027], ['learning_objective', '変更された技能'],
    ['source_urls', ['https://www.mhlw.go.jp/']], ['reference_sheet_slugs', ['changed-table']],
]);

test('参照表改訂は関連問題だけを再監査に戻し既存習熟を再確認させる', function () {
    $question = Question::where('source_id', 'q-0676')->firstOrFail();
    $other = Question::where('source_id', 'q-0032')->firstOrFail();
    $sheet = ReferenceSheet::where('slug', $question->reference_sheet_slugs[0])->firstOrFail();
    $user = User::factory()->create();
    $user->questionProgresses()->create(['question_id' => $question->id, 'state' => 'mastered', 'box' => 5, 'due_at' => now()->addMonth(), 'content_revision_seen' => 1]);
    $sheet->update(['content' => array_replace($sheet->content, ['notes' => ['参照表変更のテスト']])]);
    expect($question->refresh()->review_status)->toBe(QuestionReviewStatus::InReview)
        ->and($question->content_revision)->toBe(2)
        ->and($other->refresh()->review_status)->toBe(QuestionReviewStatus::Approved)
        ->and($user->questionProgresses()->first()->state)->toBe('learning');
});

test('管理画面だけで未承認問題を公開承認にできない', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $question->update(['review_status' => QuestionReviewStatus::InReview, 'is_active' => false]);
    $page = new class extends EditQuestion
    {
        public function prepare(Question $question, array $data): array
        {
            $this->record = $question;

            return $this->mutateFormDataBeforeSave($data);
        }
    };
    expect(fn () => $page->prepare($question, array_replace($question->toArray(), ['review_status' => 'approved', 'is_active' => true])))
        ->toThrow(ValidationException::class);
});

test('厳格監査は本文ハッシュを合わせたDB改変や参照表の直接改変も検知する', function () {
    $question = Question::where('source_id', 'q-0032')->firstOrFail();
    $changed = array_replace($question->toArray(), ['explanation' => '正本と異なる解説']);
    $hash = Question::contentHash($changed);
    $question->update(['explanation' => $changed['explanation'], 'content_hash' => $hash, 'reviewed_content_hash' => $hash]);
    ReferenceSheet::where('slug', 'kenpo-tokyo')->update(['content' => ['changed' => true]]);
    $errors = app(ContentAuditService::class)->audit()['errors'];
    expect(implode('\n', $errors))->toContain('q-0032: DBの本文', '参照表kenpo-tokyo:');
});

test('模試の正解位置は各10問を保ち単純な4問周期にならない', function () {
    foreach (MockExam::all() as $exam) {
        $keys = $exam->examQuestions()->with('question')->orderBy('position')->get()->map(fn ($item) => $item->question->answer['choice']);
        expect($keys->countBy()->all())->toEqualCanonicalizing(['A' => 10, 'B' => 10, 'C' => 10, 'D' => 10]);
        $periodic = true;
        for ($i = 4; $i < 40; $i++) {
            $periodic = $periodic && $keys[$i] === $keys[$i % 4];
        }
        expect($periodic)->toBeFalse();
    }
});
