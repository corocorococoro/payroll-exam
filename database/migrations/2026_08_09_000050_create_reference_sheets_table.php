<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_sheets', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->unsignedSmallInteger('fiscal_year');
            $table->jsonb('content');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['slug', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_sheets');
    }
};
