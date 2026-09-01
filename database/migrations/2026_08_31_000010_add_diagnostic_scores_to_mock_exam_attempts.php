<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_exam_attempts', function (Blueprint $table) {
            $table->jsonb('unit_scores')->nullable()->after('section_scores');
            $table->unsignedSmallInteger('knowledge_score')->nullable()->after('unit_scores');
            $table->unsignedSmallInteger('calculation_score')->nullable()->after('knowledge_score');
        });
    }

    public function down(): void
    {
        Schema::table('mock_exam_attempts', function (Blueprint $table) {
            $table->dropColumn(['unit_scores', 'knowledge_score', 'calculation_score']);
        });
    }
};
