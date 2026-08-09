<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_quests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('quest_type');
            $table->unsignedSmallInteger('target');
            $table->unsignedSmallInteger('progress')->default(0);
            $table->boolean('completed')->default(false);
            $table->unsignedSmallInteger('xp_reward');
            $table->timestamps();
            $table->index(['user_id', 'date']);
        });

        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description');
            $table->string('icon');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_badges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();
            $table->unique(['user_id', 'badge_id']);
        });

        Schema::create('league_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedInteger('xp')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'week_start']);
            $table->index(['week_start', 'xp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_scores');
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
        Schema::dropIfExists('daily_quests');
    }
};
