<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('amount');
            $table->string('source_type', 32);
            $table->string('source_key', 120);
            $table->timestamps();
            $table->unique(['user_id', 'source_key']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_reward_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reward_slug', 64);
            $table->timestamp('unlocked_at');
            $table->timestamps();
            $table->unique(['user_id', 'reward_slug']);
        });

        Schema::table('user_stats', function (Blueprint $table) {
            $table->string('mascot_style', 64)->default('default')->after('total_xp');
        });
    }

    public function down(): void
    {
        Schema::table('user_stats', function (Blueprint $table) {
            $table->dropColumn('mascot_style');
        });

        Schema::dropIfExists('user_reward_unlocks');
        Schema::dropIfExists('xp_transactions');
    }
};
