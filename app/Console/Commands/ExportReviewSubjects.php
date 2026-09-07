<?php

namespace App\Console\Commands;

use App\Services\QuestionReviewLedger;
use Illuminate\Console\Command;

class ExportReviewSubjects extends Command
{
    protected $signature = 'content:review-subjects';

    protected $description = '監査対象と依存資料の指紋をJSONで出力する（承認は変更しない）';

    public function handle(QuestionReviewLedger $ledger): int
    {
        $subjects = $ledger->subjects();
        foreach ($subjects as &$subject) {
            $subject['fingerprint'] = QuestionReviewLedger::fingerprint($subject);
        }
        unset($subject);
        $this->line(json_encode($subjects, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
