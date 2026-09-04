<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class GamificationSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['slug' => 'first-step', 'name' => 'はじめの一歩', 'description' => '最初の問題に挑戦した', 'icon' => '🐣'],
            ['slug' => 'xp-100', 'name' => 'XPルーキー', 'description' => '合計100 XPを獲得した', 'icon' => '✨'],
            ['slug' => 'xp-1000', 'name' => 'XPスター', 'description' => '合計1,000 XPを獲得した', 'icon' => '🌟'],
            ['slug' => 'streak-7', 'name' => '一週間継続', 'description' => '7日連続で目標を達成した', 'icon' => '🔥'],
            ['slug' => 'streak-30', 'name' => '習慣の達人', 'description' => '30日連続で目標を達成した', 'icon' => '🏃'],
            ['slug' => 'streak-100', 'name' => '継続の神様', 'description' => '100日連続で目標を達成した', 'icon' => '👑'],
            ['slug' => 'lesson-master', 'name' => 'レッスン完了の達人', 'description' => '同じレッスンで完了ボーナスを5回獲得した', 'icon' => '🏆'],
            ['slug' => 'mock-pass', 'name' => '合格ライン突破', 'description' => '模試で70点以上を獲得した', 'icon' => '🎉'],
            ['slug' => 'mock-80', 'name' => '80点突破', 'description' => '模試で80点以上を獲得した', 'icon' => '🥇'],
            ['slug' => 'mock-90', 'name' => '給与計算エース', 'description' => '模試で90点以上を獲得した', 'icon' => '💯'],
        ];

        foreach ($badges as $index => $badge) {
            Badge::updateOrCreate(['slug' => $badge['slug']], [...$badge, 'sort_order' => $index + 1]);
        }
    }
}
