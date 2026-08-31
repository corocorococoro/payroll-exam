<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('study_tier')->default('reinforcement')->after('exam_role')->index();
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->jsonb('study_guide')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('study_guide');
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['study_tier']);
            $table->dropColumn('study_tier');
        });
    }
};
