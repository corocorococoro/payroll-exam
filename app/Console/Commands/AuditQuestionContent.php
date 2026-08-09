<?php

namespace App\Console\Commands;

use App\Services\ContentAuditService;
use Illuminate\Console\Command;

class AuditQuestionContent extends Command
{
    protected $signature = 'content:audit {--strict : 警告も失敗として扱う}';

    protected $description = '公開問題の重複・根拠・レビュー期限・模試構成を監査する';

    public function handle(ContentAuditService $auditService): int
    {
        $result = $auditService->audit();

        $this->table(['項目', '件数'], collect($result['stats'])->map(
            fn (int $value, string $key): array => [$key, $value],
        )->values()->all());

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        if ($result['errors'] !== [] || ($this->option('strict') && $result['warnings'] !== [])) {
            return self::FAILURE;
        }

        $this->info('公開コンテンツの品質監査に合格しました。');

        return self::SUCCESS;
    }
}
