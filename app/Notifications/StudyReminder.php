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
            ->subject('きゅーちゃんと今日の学習を続けよう 🔥')
            ->greeting("{$name}さん、こんばんは！")
            ->line('今日はまだデイリーゴールを達成していません。')
            ->line('少しだけでも復習して、ストリークをつなげましょう。')
            ->action('今日の学習をはじめる', route('dashboard'))
            ->line('きゅーちゃんが待っています！');
    }
}
