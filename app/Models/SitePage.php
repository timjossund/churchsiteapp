<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $site_id
 * @property string $name
 * @property int $position
 * @property bool $is_home
 */
#[Fillable(['name', 'position', 'is_home'])]
class SitePage extends Model
{
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
