<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_hostnames', function (Blueprint $table) {
            $table->id();
            // Keep remote-operation identity until cleanup is confirmed, even during account deletion.
            $table->foreignId('site_id')->unique()->constrained()->restrictOnDelete();
            $table->string('hostname', 253)->unique();
            $table->string('cloudflare_id')->nullable()->unique();
            $table->uuid('operation_id')->unique();
            $table->string('state')->default('reserved');
            $table->string('hostname_status')->default('pending');
            $table->string('ssl_status')->default('pending');
            $table->string('ownership_challenge', 64);
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('dns_instructions')->nullable();
            $table->string('error_category')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_hostnames');
    }
};
