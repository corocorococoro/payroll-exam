<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('section');
            $table->text('goal');
            $table->text('why');
            $table->string('method');
            $table->text('memory_tip')->nullable();
            $table->json('approach');
            $table->json('prerequisites');
            $table->foreignId('example_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('content_hash', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('learning_module_question', function (Blueprint $table) {
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->primary(['learning_module_id', 'question_id']);
            $table->index('question_id');
        });

        Schema::create('learning_module_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_module_id')->constrained()->cascadeOnDelete();
            $table->string('content_hash', 64);
            $table->timestamp('guided_completed_at')->nullable();
            $table->timestamp('independent_passed_at')->nullable();
            $table->timestamp('spaced_passed_at')->nullable();
            $table->timestamp('review_due_at')->nullable();
            $table->json('independent_concepts')->nullable();
            $table->json('spaced_concepts')->nullable();
            $table->boolean('needs_support')->default(false);
            $table->unsignedInteger('completed_count')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'learning_module_id']);
        });

        Schema::table('question_attempts', function (Blueprint $table) {
            $table->foreignId('learning_module_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('study_run_id')->nullable()->index();
            $table->boolean('assisted')->default(false)->index();
        });
        Schema::create('study_run_completions', function (Blueprint $table) {
            $table->uuid('study_run_id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_run_completions');
        Schema::table('question_attempts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('learning_module_id');
            $table->dropIndex(['study_run_id']);
            $table->dropIndex(['assisted']);
            $table->dropColumn(['study_run_id', 'assisted']);
        });
        Schema::dropIfExists('learning_module_progress');
        Schema::dropIfExists('learning_module_question');
        Schema::dropIfExists('learning_modules');
    }
};
