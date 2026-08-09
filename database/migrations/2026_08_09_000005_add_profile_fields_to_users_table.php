<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
            $table->unsignedSmallInteger('daily_goal')->default(20);
            $table->time('reminder_time')->nullable();
            $table->boolean('reminder_enabled')->default(false);
            $table->date('exam_date')->nullable();
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('onboarded')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_admin', 'daily_goal', 'reminder_time', 'reminder_enabled',
                'exam_date', 'sound_enabled', 'onboarded',
            ]);
        });
    }
};
