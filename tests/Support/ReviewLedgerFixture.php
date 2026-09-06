<?php

namespace Tests\Support;

use App\Services\QuestionReviewLedger;

/** Synthetic editorial decisions for application behavior tests, never released. */
class ReviewLedgerFixture
{
    /** @return array<string, array<string, mixed>> */
    public static function records(): array
    {
        $records = [];
        foreach ((new QuestionReviewLedger)->subjects() as $id => $subject) {
            $key = array_key_first($subject['sources']);
            $records[$id] = [
                'decision' => 'maintain', 'verification' => 'complete',
                'fingerprint' => QuestionReviewLedger::fingerprint($subject),
                'reviewed_at' => today()->toDateString(), 'review_due_at' => today()->addYear()->toDateString(),
                'notes' => '動作テスト用の架空の承認。教材の正確性を保証しない。',
                'checks' => array_fill_keys(QuestionReviewLedger::CHECKS, '動作テスト用の確認結果'),
                'evidence' => [['source_key' => $key, 'url' => $subject['sources'][$key]['url'], 'locator' => 'テスト用の参照箇所']],
            ];
        }

        return $records;
    }
}
