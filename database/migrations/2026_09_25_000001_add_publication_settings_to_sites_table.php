<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->unique();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('social_image_id')
                ->nullable()
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->json('published_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_image_id');
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'slug',
                'seo_title',
                'seo_description',
                'published_snapshot',
                'published_at',
            ]);
        });
    }
};
