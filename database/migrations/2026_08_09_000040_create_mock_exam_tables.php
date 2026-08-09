<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('time_limit_minutes')->default(120);
            $table->unsignedSmallInteger('passing_score')->default(70);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('mock_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('points');
            $table->timestamps();
            $table->unique(['mock_exam_id', 'position']);
        });

        Schema::create('mock_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_exam_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('time_limit_minutes');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->jsonb('answers')->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->jsonb('section_scores')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'mock_exam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_exam_attempts');
        Schema::dropIfExists('mock_exam_questions');
        Schema::dropIfExists('mock_exams');
    }
};
