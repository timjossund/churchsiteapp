<?php

namespace App\Models;

use App\Support\SitePagePath;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $site_id
 * @property string $name
 * @property string|null $path
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property int|null $social_image_id
 * @property int $position
 * @property bool $is_home
 */
#[Fillable(['name', 'position', 'is_home', 'path', 'seo_title', 'seo_description', 'social_image_id'])]
class SitePage extends Model
{
    protected static function booted(): void
    {
        static::created(function (SitePage $page): void {
            if (! $page->is_home && $page->path === null) {
                $page->update(['path' => SitePagePath::generate($page->site_id, $page->id, $page->name)]);
            }
        });
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function socialImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'social_image_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_home' => 'boolean'];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<SiteBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(SiteBlock::class, 'page_id');
    }
}
