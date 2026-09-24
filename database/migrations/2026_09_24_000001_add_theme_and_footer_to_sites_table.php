<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('theme_key')->default('warm');
            $table->json('footer')->nullable();
        });

        DB::table('sites')
            ->whereNull('footer')
            ->update(['footer' => '{"text":""}']);
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['theme_key', 'footer']);
        });
    }
};
