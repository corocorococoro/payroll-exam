<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('context');
            $table->boolean('is_correct');
            $table->jsonb('given_answer')->nullable();
            $table->unsignedSmallInteger('xp_earned')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'question_id']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('crown_level')->default(0);
            $table->unsignedSmallInteger('completed_count')->default(0);
            $table->timestamp('last_completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'lesson_id']);
        });

        Schema::create('user_stats', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('total_xp')->default(0);
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->unsignedSmallInteger('longest_streak')->default(0);
            $table->date('last_active_date')->nullable();
            $table->unsignedSmallInteger('streak_freezes')->default(1);
            $table->timestamps();
        });

        Schema::create('daily_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('xp')->default(0);
            $table->unsignedSmallInteger('questions_answered')->default(0);
            $table->boolean('goal_met')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });

        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('box')->default(1);
            $table->date('due_date');
            $table->unsignedSmallInteger('lapses')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'question_id']);
            $table->index(['user_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('daily_activities');
        Schema::dropIfExists('user_stats');
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('question_attempts');
    }
};
