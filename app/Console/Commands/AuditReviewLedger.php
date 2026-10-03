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
        $reminders = $ledger->reminders();
        $result = ['questions' => count($ledger->subjects()), 'errors' => $errors, 'reminders' => $reminders];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } else {
            $this->info("監査対象{$result['questions']}問、未解決".count($errors).'件');
            foreach ($errors as $error) {
                $this->error($error);
            }
            if ($reminders !== []) {
                $this->warn('再確認待ち'.count($reminders).'件（再確認日の超過では同期・出題を停止しません）。');
                foreach (array_slice($reminders, 0, 5) as $reminder) {
                    $this->warn($reminder);
                }
            }
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
