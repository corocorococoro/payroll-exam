<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('content_revision')->default(1)->after('question_id');
            $table->index(['user_id', 'question_id', 'content_revision']);
        });

        // 既存履歴は移行時点の問題版に属するものとして記録する。
        // 以後の内容改訂だけが新版の再学習・再XP対象になる。
        DB::statement(<<<'SQL'
            UPDATE question_attempts AS attempts
            SET content_revision = questions.content_revision
            FROM questions
            WHERE questions.id = attempts.question_id
            SQL);
    }

    public function down(): void
    {
        Schema::table('question_attempts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'question_id', 'content_revision']);
            $table->dropColumn('content_revision');
        });
    }
};
