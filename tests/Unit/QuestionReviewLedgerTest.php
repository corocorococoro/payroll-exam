<?php

use App\Services\QuestionReviewLedger;
use Illuminate\Support\Facades\File;
use Tests\Support\ReviewLedgerFixture;

test('監査台帳は欠番・未確認・旧内容・根拠欠落を承認しない', function () {
    $records = ReviewLedgerFixture::records();
    unset($records['q-0001']);
    $records['q-0002']['fingerprint'] = str_repeat('0', 64);
    $records['q-0003']['evidence'] = [];
    $records['q-0004']['checks']['all_choices'] = '';
    $records['q-0005']['reviewed_at'] = today()->addDay()->toDateString();
    $errors = (new QuestionReviewLedger($records))->errors();
    expect(implode('\n', $errors))->toContain('q-0001:', 'q-0002:', 'q-0003:', 'q-0004:', 'q-0005:');
    expect(fn () => (new QuestionReviewLedger($records))->approvedRecords())->toThrow(RuntimeException::class);
});

test('監査対象は問題以外の学習目標・根拠・参照表・説明の変更も検知する', function () {
    $subject = array_values((new QuestionReviewLedger)->subjects())[0];
    $before = QuestionReviewLedger::fingerprint($subject);
    foreach (['question', 'legal_as_of', 'learning_objective', 'sources', 'reference_sheets', 'lesson'] as $key) {
        $changed = $subject;
        $changed[$key] = '変更後';
        expect(QuestionReviewLedger::fingerprint($changed))->not->toBe($before);
    }
    expect(QuestionReviewLedger::fingerprint(array_reverse($subject, true)))->toBe($before);
});

test('監査期限切れ・別URL・理由のない退役を検出する', function () {
    $records = ReviewLedgerFixture::records();
    $records['q-0001']['review_due_at'] = today()->subDay()->toDateString();
    $records['q-0002']['evidence'][0]['url'] = 'https://example.com';
    $records['removed'] = ['decision' => 'retire', 'notes' => ''];
    expect(implode('\n', (new QuestionReviewLedger($records))->errors()))->toContain('q-0001:', 'q-0002:', 'removed:');
});

test('別資料キーで同じURLの表示名を上書きしても公開承認を通さない', function () {
    $records = ReviewLedgerFixture::records();
    $path = database_path('seeders/data/official-sources.json');
    $sources = File::json($path);
    $sources['unreviewed_alias'] = [
        'label' => '監査していない別の説明',
        'url' => $sources['labor_standards']['url'],
    ];
    File::partialMock()->shouldReceive('json')->with($path)->andReturn($sources);

    $ledger = new QuestionReviewLedger($records);
    expect(implode('\n', $ledger->errors()))->toContain('unreviewed_alias', 'URLがlabor_standardsと重複');
    expect(fn () => $ledger->approvedRecords())->toThrow(RuntimeException::class);
});

test('退役の本文改変と参照根拠の欠落を検出する', function () {
    $records = ReviewLedgerFixture::records();
    $retired = File::json(database_path('seeders/data/question-reviews.json'))['questions']['q-0028'];
    $records['q-0028'] = $retired;
    expect((new QuestionReviewLedger($records))->errors())->toBe([]);

    $records['q-0028']['retired_question']['answer'] = ['choice' => 'A'];
    expect(implode('\n', (new QuestionReviewLedger($records))->errors()))->toContain('退役時の問題内容と保存ハッシュが一致しません');

    $records['q-0028'] = $retired;
    $records['q-0028']['evidence'] = [];
    expect(implode('\n', (new QuestionReviewLedger($records))->errors()))->toContain('退役判断の参照根拠がありません');

    $records['q-0028'] = $retired;
    $records['q-0028']['evidence'][0]['url'] = 'https://example.com/unreviewed';
    expect(implode('\n', (new QuestionReviewLedger($records))->errors()))->toContain('退役判断の資料キー・URL・該当箇所が対応していません');
    unset($records['q-0028']['evidence'][0]['source_key']);
    expect(implode('\n', (new QuestionReviewLedger($records))->errors()))->toContain('退役判断の資料キー・URL・該当箇所が対応していません');
});

test('全問題を個別確認しても教材全体の公開承認がなければ同期を拒否する', function () {
    $bankPath = database_path('seeders/data/question-bank.json');
    $reviewPath = database_path('seeders/data/question-reviews.json');
    $bank = File::json($bankPath);
    $reviews = File::json($reviewPath);
    $reviews['questions'] = array_replace($reviews['questions'], ReviewLedgerFixture::records());
    $bank['release']['editorial_status'] = 'audit_in_progress';
    File::partialMock()->shouldReceive('json')->with($reviewPath)->andReturn($reviews);
    File::shouldReceive('json')->with($bankPath)->andReturnUsing(function () use (&$bank) {
        return $bank;
    });

    $ledger = new QuestionReviewLedger;
    expect($ledger->errors())->toBe([
        '教材全体の横断監査・公開承認が未完了です。個別問題の確認完了だけでは同期できません。',
    ]);
    expect(fn () => $ledger->approvedRecords())->toThrow(RuntimeException::class);

    // Approval is synthetic and local to this test; no canonical files change.
    $bank['release']['editorial_status'] = 'approved';
    expect($ledger->errors())->toBe([]);
    expect($ledger->approvedRecords())->toHaveKey('q-0837');
});
