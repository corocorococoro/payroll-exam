<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->string('review_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn('review_fingerprint');
        });
    }
};
