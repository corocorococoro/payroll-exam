<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DailyQuestService;
use Illuminate\Console\Command;

class GenerateDailyQuests extends Command
{
    protected $signature = 'quests:generate';

    protected $description = '全ユーザーのデイリークエストを生成する';

    public function handle(DailyQuestService $quests): int
    {
        User::query()->chunkById(100, function ($users) use ($quests): void {
            foreach ($users as $user) {
                $quests->ensureToday($user);
            }
        });

        return self::SUCCESS;
    }
}
