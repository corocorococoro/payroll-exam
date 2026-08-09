<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('concept_key')->nullable()->after('source_id')->index();
            $table->string('review_status')->default('draft')->after('difficulty')->index();
            $table->unsignedSmallInteger('content_revision')->default(1)->after('review_status');
            $table->string('content_hash', 64)->nullable()->after('content_revision');
            $table->string('reviewed_content_hash', 64)->nullable()->after('content_hash');
            $table->jsonb('source_urls')->nullable()->after('reference_sheet_slugs');
            $table->text('review_notes')->nullable()->after('source_urls');
            $table->timestamp('reviewed_at')->nullable()->after('review_notes');
            $table->timestamp('review_due_at')->nullable()->after('reviewed_at')->index();
        });

        Schema::table('mock_exams', function (Blueprint $table) {
            $table->boolean('is_published')->default(true)->after('sort_order')->index();
        });

        DB::table('questions')
            ->where('source_id', 'like', 'gen-%')
            ->update([
                'is_active' => false,
                'review_status' => 'retired',
                'review_notes' => '少数の原型に前置きと数値だけを変えた生成問題のため公開停止',
            ]);

        DB::table('mock_exams')
            ->whereIn('slug', ['mogi-2', 'mogi-3'])
            ->update(['is_published' => false]);
    }

    public function down(): void
    {
        Schema::table('mock_exams', function (Blueprint $table) {
            $table->dropColumn('is_published');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['concept_key']);
            $table->dropIndex(['review_status']);
            $table->dropIndex(['review_due_at']);
            $table->dropColumn([
                'concept_key',
                'review_status',
                'content_revision',
                'content_hash',
                'reviewed_content_hash',
                'source_urls',
                'review_notes',
                'reviewed_at',
                'review_due_at',
            ]);
        });
    }
};
