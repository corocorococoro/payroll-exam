<?php

namespace App\Console\Commands;

use App\Services\QuestionReviewLedger;
use Illuminate\Console\Command;

class AuditReviewLedger extends Command
{
    protected $signature = 'content:review-audit {--json : JSONで検証結果を表示する}';

    protected $description = 'DBを使わず、全問の監査台帳と正本・資料の一致を検証する';

    public function handle(QuestionReviewLedger $ledger): int
    {
        $errors = $ledger->errors();
        $result = ['questions' => count($ledger->subjects()), 'errors' => $errors];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info("監査対象{$result['questions']}問、未解決".count($errors).'件');
            foreach ($errors as $error) {
                $this->error($error);
            }
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
