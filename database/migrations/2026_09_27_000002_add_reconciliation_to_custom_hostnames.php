<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_hostnames', function (Blueprint $table) {
            $table->string('cloudflare_zone_id', 32)->nullable();
            $table->timestamp('provision_started_at')->nullable();
            $table->uuid('check_id')->nullable();
            $table->timestamp('check_started_at')->nullable();
            $table->boolean('cname_matches')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('custom_hostnames', function (Blueprint $table) {
            $table->dropColumn(['cloudflare_zone_id', 'provision_started_at', 'check_id', 'check_started_at', 'cname_matches']);
        });
    }
};
