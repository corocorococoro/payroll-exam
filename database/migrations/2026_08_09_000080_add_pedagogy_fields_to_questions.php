<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('learning_objective')->nullable()->after('concept_key');
            $table->string('variant_role')->nullable()->after('learning_objective')->index();
            $table->string('misconception_key')->nullable()->after('variant_role')->index();
            $table->jsonb('distractor_feedback')->nullable()->after('common_mistake');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['variant_role']);
            $table->dropIndex(['misconception_key']);
            $table->dropColumn([
                'learning_objective',
                'variant_role',
                'misconception_key',
                'distractor_feedback',
            ]);
        });
    }
};
