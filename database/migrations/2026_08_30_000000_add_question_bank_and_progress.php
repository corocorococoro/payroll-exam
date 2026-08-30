<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('verification_status')->default('primary_source_checked')->after('review_status')->index();
            $table->string('scope_status')->default('core')->after('verification_status')->index();
            $table->string('exam_role')->default('knowledge')->after('scope_status')->index();
        });

        Schema::create('user_question_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('state')->default('new');
            $table->unsignedTinyInteger('box')->default(0);
            $table->timestamp('due_at')->nullable();
            $table->unsignedSmallInteger('lapses')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('incorrect_count')->default(0);
            $table->unsignedSmallInteger('content_revision_seen')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'question_id']);
            $table->index(['user_id', 'state']);
            $table->index(['user_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_question_progress');

        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn([
                'verification_status',
                'scope_status',
                'exam_role',
            ]);
        });
    }
};
