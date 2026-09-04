<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateBadges([
            'streak-7' => ['name' => '一週間継続', 'description' => '7日連続で目標を達成した'],
            'streak-30' => ['name' => '習慣の達人', 'description' => '30日連続で目標を達成した'],
            'streak-100' => ['name' => '継続の神様', 'description' => '100日連続で目標を達成した'],
            'lesson-master' => ['name' => 'レッスン完了の達人', 'description' => '同じレッスンで完了ボーナスを5回獲得した'],
            'mock-80' => ['name' => '80点突破', 'description' => '模試で80点以上を獲得した'],
        ]);
    }

    public function down(): void
    {
        $this->updateBadges([
            'streak-7' => ['name' => '一週間継続', 'description' => '7日ストリークを達成した'],
            'streak-30' => ['name' => '習慣の達人', 'description' => '30日ストリークを達成した'],
            'streak-100' => ['name' => '継続の神様', 'description' => '100日ストリークを達成した'],
            'lesson-master' => ['name' => 'クラウンマスター', 'description' => 'レッスンをクラウン5にした'],
            'mock-80' => ['name' => '安定合格圏', 'description' => '模試で80点以上を獲得した'],
        ]);
    }

    /**
     * @param  array<string, array{name: string, description: string}>  $badges
     */
    private function updateBadges(array $badges): void
    {
        foreach ($badges as $slug => $copy) {
            DB::table('badges')->where('slug', $slug)->update($copy);
        }
    }
};
