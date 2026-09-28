<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

/**
 * @property CarbonImmutable|null $deletion_requested_at
 * @property int $id
 * @property CarbonImmutable|null $checkout_started_at
 * @property int $user_id
 * @property string $name
 * @property array{font_pairing?: string, accent_color?: string|null, button_shape?: string}|null $appearance
 * @property string $theme_key
 * @property string|null $slug
 * @property array{text: string} $footer
 * @property int|null $logo_media_asset_id
 * @property array<string, mixed>|null $published_snapshot
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'theme_key', 'appearance', 'footer', 'logo_media_asset_id', 'slug', 'published_snapshot', 'published_at'])]
#[Hidden(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at', 'checkout_attempt', 'checkout_started_at', 'checkout_price_id', 'checkout_session_id'])]
class Site extends Model
{
    protected $attributes = [
        'theme_key' => 'warm',
        'footer' => '{"text":""}',
    ];

    use Billable;

    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    /** @return HasOne<CustomHostname, $this> */
    public function customHostname(): HasOne
    {
        return $this->hasOne(CustomHostname::class);
    }

    public function billingSubscription(): ?Subscription
    {
        // Webhook arrival time is not subscription chronology. Current commitments take precedence.
        return Subscription::query()
            ->where('site_id', $this->id)
            ->where('type', 'default')
            ->orderByRaw("CASE WHEN stripe_status IN ('canceled', 'incomplete_expired') THEN 1 ELSE 0 END")
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->first();
    }

    public function hasPaidDomainAccess(): bool
    {
        if ($this->deletion_requested_at !== null) {
            return false;
        }

        return $this->subscriptions()
            ->where('type', 'default')
            ->where('stripe_status', 'active')
            ->where('paid_until', '>', now())
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->exists();
    }

    public function stripeEmail(): ?string
    {
        return $this->user?->email;
    }

    protected static function booted(): void
    {
        static::created(function (Site $site): void {
            $site->pages()->create(['name' => 'Home', 'position' => 0, 'is_home' => true]);
        });
    }

    /** @return HasMany<SitePage, $this> */
    public function pages(): HasMany
    {
        return $this->hasMany(SitePage::class);
    }

    /** @return HasOne<SitePage, $this> */
    public function homePage(): HasOne
    {
        return $this->hasOne(SitePage::class)->where('is_home', true);
    }

    public function editorPage(int|string|null $pageId): SitePage
    {
        abort_if($this->deletion_requested_at !== null, 404);

        return $pageId === null
            ? $this->homePage()->firstOrFail()
            : $this->pages()->whereKey($pageId)->firstOrFail();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'footer' => 'array',
            'appearance' => 'array',
            'published_snapshot' => 'array',
            'published_at' => 'immutable_datetime',
            'deletion_requested_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'checkout_started_at' => 'immutable_datetime',
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
}
