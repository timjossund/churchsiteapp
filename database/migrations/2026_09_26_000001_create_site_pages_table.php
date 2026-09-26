<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position');
            $table->boolean('is_home')->default(false);
            $table->timestamps();
            $table->index(['site_id', 'position']);
        });

        Schema::table('site_blocks', function (Blueprint $table) {
            $table->foreignId('page_id')->nullable()->constrained('site_pages')->cascadeOnDelete();
            $table->index(['page_id', 'position']);
        });

        DB::table('sites')->orderBy('id')->chunkById(100, function ($sites) {
            foreach ($sites as $site) {
                $pageId = DB::table('site_pages')->insertGetId([
                    'site_id' => $site->id,
                    'name' => 'Home',
                    'position' => 0,
                    'is_home' => true,
                    'created_at' => $site->created_at,
                    'updated_at' => $site->updated_at,
                ]);

                DB::table('site_blocks')->where('site_id', $site->id)->update(['page_id' => $pageId]);
            }
        });

        Schema::table('site_blocks', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_blocks', function (Blueprint $table) {
            $table->dropIndex(['page_id', 'position']);
            $table->dropConstrainedForeignId('page_id');
        });
        Schema::dropIfExists('site_pages');
    }
};
