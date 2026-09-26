<?php

namespace App\Models;

use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $theme_key
 * @property string|null $slug
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property array{text: string} $footer
 * @property int|null $logo_media_asset_id
 * @property int|null $social_image_id
 * @property array<string, mixed>|null $published_snapshot
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'theme_key', 'footer', 'logo_media_asset_id', 'slug', 'seo_title', 'seo_description', 'social_image_id', 'published_snapshot', 'published_at'])]
class Site extends Model
{
    protected $attributes = [
        'theme_key' => 'warm',
        'footer' => '{"text":""}',
    ];

    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'footer' => 'array',
            'published_snapshot' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<SiteBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(SiteBlock::class);
    }

    /** @return HasMany<MediaAsset, $this> */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function logoMediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'logo_media_asset_id');
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function socialImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'social_image_id');
    }
}
