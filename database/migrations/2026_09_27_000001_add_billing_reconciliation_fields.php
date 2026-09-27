<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->uuid('checkout_attempt')->nullable();
            $table->timestamp('checkout_started_at')->nullable();
            $table->string('checkout_price_id')->nullable();
            $table->string('checkout_session_id')->nullable();
        });
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('paid_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('paid_until'));
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn([
            'checkout_attempt', 'checkout_started_at', 'checkout_price_id', 'checkout_session_id',
        ]));
    }
};
