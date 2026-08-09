<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\StreakService;
use Illuminate\Console\Command;

class MaintainStreaks extends Command
{
    protected $signature = 'streaks:maintain';

    protected $description = 'ストリーク切れを判定し、月曜はフリーズを補充する';

    public function handle(StreakService $streaks): int
    {
        User::query()->chunkById(100, function ($users) use ($streaks): void {
            foreach ($users as $user) {
                $streaks->applyOvernightCheck($user);

                if (today()->isMonday()) {
                    $streaks->grantWeeklyFreeze($user);
                }
            }
        });

        return self::SUCCESS;
    }
}
