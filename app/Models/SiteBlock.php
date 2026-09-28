<?php

namespace App\Models;

use Database\Factories\SiteBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $page_id
 * @property int $site_id
 * @property string $type
 * @property int $position
 * @property array<string, mixed> $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'position', 'content'])]
class SiteBlock extends Model
{
    /** @use HasFactory<SiteBlockFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SiteBlock $block): void {
            $attributes = $block->getAttributes();
            if (! isset($attributes['page_id'])) {
                $block->page_id = Site::query()->findOrFail($block->site_id)->homePage()->firstOrFail()->id;
            }

            $page = SitePage::query()->findOrFail($block->page_id);
            if (isset($attributes['site_id']) && $block->site_id !== $page->site_id) {
                throw new \LogicException('A block must belong to the same site as its page.');
            }
            $block->site_id = $page->site_id;
        });
    }

    /** @return BelongsTo<SitePage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'page_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function disableLinksTo(string $type, int $target): void
    {
        $content = $this->content;
        $field = $type === 'page' ? 'target_page_id' : 'target_block_id';
        foreach (['primary', 'secondary'] as $slot) {
            $button = $slot === 'primary' ? $content : ($content['secondary_button'] ?? []);
            if (($button['link_type'] ?? null) !== $type || (int) ($button[$field] ?? 0) !== $target) {
                continue;
            }
            $button['link_type'] = 'none';
            $button['button_label'] = '';
            $button['target_block_id'] = null;
            $button['external_url'] = '';
            if (array_key_exists('target_page_id', $button)) {
                $button['target_page_id'] = null;
            }
            if ($slot === 'primary') {
                $content = $button;
            } else {
                $content['secondary_button'] = $button;
            }
        }
        if ($content !== $this->content) {
            $this->update(['content' => $content]);
        }
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
