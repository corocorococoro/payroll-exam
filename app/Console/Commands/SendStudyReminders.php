<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\StudyReminder;
use Illuminate\Console\Command;

class SendStudyReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = '未達成ユーザーへ設定時刻に学習リマインダーを送信する';

    public function handle(): int
    {
        $currentTime = now()->format('H:i');
        $sent = 0;

        User::query()
            ->where('reminder_enabled', true)
            ->whereNotNull('reminder_time')
            ->where(fn ($query) => $query
                ->whereNull('last_reminded_on')
                ->orWhereDate('last_reminded_on', '<', today()))
            ->with('stat')
            ->chunkById(100, function ($users) use ($currentTime, &$sent): void {
                foreach ($users as $user) {
                    if (substr((string) $user->reminder_time, 0, 5) !== $currentTime) {
                        continue;
                    }

                    $goalMet = $user->dailyActivities()
                        ->whereDate('date', today())
                        ->where('goal_met', true)
                        ->exists();

                    if ($goalMet) {
                        continue;
                    }

                    $user->notify(new StudyReminder);
                    $user->forceFill(['last_reminded_on' => today()])->save();
                    $sent++;
                }
            });

        $this->info("{$sent}件のリマインダーを送信しました。");

        return self::SUCCESS;
    }
}
