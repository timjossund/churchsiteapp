<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->unsignedInteger('position');
            $table->json('content');
            $table->timestamps();

            $table->index(['site_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_blocks');
    }
};
