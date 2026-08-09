<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->boolean('is_advanced')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['course_id', 'slug']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['unit_id', 'slug']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_id')->nullable()->unique();
            $table->string('type');
            $table->string('category');
            $table->string('difficulty');
            $table->unsignedSmallInteger('fiscal_year');
            $table->text('question_text');
            $table->jsonb('choices')->nullable();
            $table->jsonb('answer');
            $table->text('explanation');
            $table->text('common_mistake')->nullable();
            $table->jsonb('calc_params')->nullable();
            $table->jsonb('reference_sheet_slugs')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['lesson_id', 'is_active']);
            $table->index(['unit_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('units');
        Schema::dropIfExists('courses');
    }
};
