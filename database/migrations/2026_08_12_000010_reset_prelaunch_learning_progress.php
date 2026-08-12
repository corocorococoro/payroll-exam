<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * リリース前のXP再設計に伴い、旧ルールで作られた学習進捗だけを破棄する。
     * アカウント・プロフィール・通知設定と問題コンテンツは保持する。
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'xp_transactions',
                'user_reward_unlocks',
                'daily_quests',
                'daily_activities',
                'league_scores',
                'user_badges',
                'review_items',
                'question_attempts',
                'lesson_progress',
                'mock_exam_attempts',
                'user_stats',
            ] as $table) {
                DB::table($table)->delete();
            }
        });
    }

    public function down(): void
    {
        // 旧XPルールの進捗は意図的に復元しない。
    }
};
