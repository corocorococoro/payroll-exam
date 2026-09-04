<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StudyReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->name : '';

        return (new MailMessage)
            ->subject('今日の学習のお知らせ')
            ->greeting("{$name}さん、こんばんは！")
            ->line('今日はまだ1日のXP目標を達成していません。')
            ->line('少しだけでも学習して、連続記録を伸ばしましょう。')
            ->action('今日の学習を始める', route('dashboard'))
            ->line('無理のない範囲で取り組んでください。');
    }
}
