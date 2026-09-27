<?php

use App\Support\SitePagePath;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            $table->string('path', 100)->nullable();
            $table->unique(['site_id', 'path']);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            if (DB::getDriverName() !== 'sqlite') {
                $table->foreignId('social_image_id')->nullable()->constrained('media_assets')->nullOnDelete();
            }
        });

        // A SQLite table rebuild inside a transaction cascades deletion to site_blocks.
        // SQLite supports adding this nullable reference directly, without rebuilding.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE site_pages ADD COLUMN social_image_id INTEGER REFERENCES media_assets(id) ON DELETE SET NULL');
        }

        DB::table('sites')->orderBy('id')->chunkById(100, function ($sites): void {
            foreach ($sites as $site) {
                DB::transaction(function () use ($site): void {
                    $lockedSite = DB::table('sites')->where('id', $site->id)->lockForUpdate()->first();
                    DB::table('site_pages')->where('site_id', $site->id)->where('is_home', true)->update([
                        'seo_title' => $lockedSite->seo_title,
                        'seo_description' => $lockedSite->seo_description,
                        'social_image_id' => $lockedSite->social_image_id,
                    ]);
                    $pages = DB::table('site_pages')->where('site_id', $site->id)->where('is_home', false)->orderBy('id')->get();
                    foreach ($pages as $page) {
                        DB::table('site_pages')->where('id', $page->id)->update([
                            'path' => SitePagePath::generate($site->id, $page->id, $page->name),
                        ]);
                    }
                });
            }
        });
    }

    public function down(): void
    {
        Schema::table('site_pages', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') {
                $table->dropColumn('social_image_id');
            } else {
                $table->dropConstrainedForeignId('social_image_id');
            }
            $table->dropUnique(['site_id', 'path']);
            $table->dropColumn(['path', 'seo_title', 'seo_description']);
        });
    }
};
