<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SitePagePath
{
    /** Allocate while holding the site's write lock. Also used by the migration backfill. */
    public static function generate(int $siteId, int $pageId, string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', Str::slug($name)), '-');
        $base = $base === '' ? "page-{$pageId}" : $base;
        $path = rtrim(substr($base, 0, 100), '-');
        $suffix = 2;

        while (DB::table('site_pages')->where('site_id', $siteId)->where('path', $path)->exists()) {
            $ending = '-'.$suffix++;
            $path = rtrim(substr($base, 0, 100 - strlen($ending)), '-').$ending;
        }

        return $path;
    }
}
