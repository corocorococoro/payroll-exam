<?php

namespace App\Console\Commands;

use Database\Seeders\ContentSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SyncQuestionContent extends Command
{
    protected $signature = 'content:sync {--force : 同じリリースでも正本から再同期する}';

    protected $description = '問題・コース・資料集を、正本に変更がある場合だけDBへ同期する';

    public function handle(): int
    {
        $bundleHash = $this->bundleHash();
        $currentHash = DB::table('content_releases')
            ->where('name', 'question-bank')
            ->value('bundle_hash');

        if (! $this->option('force') && hash_equals((string) $currentHash, $bundleHash)) {
            $this->info('問題コンテンツは最新です。DB上のレビュー結果を保持します。');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($bundleHash): void {
            $seeder = app(ContentSeeder::class);
            $seeder->setContainer(app())->setCommand($this)->__invoke();

            DB::table('content_releases')->updateOrInsert(
                ['name' => 'question-bank'],
                [
                    'bundle_hash' => $bundleHash,
                    'applied_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });

        $this->info("問題コンテンツを同期しました（{$bundleHash}）。");

        return self::SUCCESS;
    }

    private function bundleHash(): string
    {
        $files = collect(File::allFiles(database_path('seeders/data')))
            ->map(fn (\SplFileInfo $file): string => $file->getPathname())
            ->push(database_path('seeders/ContentSeeder.php'))
            ->sort();
        $hash = hash_init('sha256');

        foreach ($files as $file) {
            $contents = File::get($file);
            $relativePath = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            hash_update($hash, $relativePath."\0".$contents."\0");
        }

        $result = hash_final($hash);

        if (strlen($result) !== 64) {
            throw new RuntimeException('問題コンテンツのハッシュを生成できませんでした。');
        }

        return $result;
    }
}
