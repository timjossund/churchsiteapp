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
 * @property int $site_id
 * @property string $type
 * @property int $position
 * @property array<string, string> $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'position', 'content'])]
class SiteBlock extends Model
{
    /** @use HasFactory<SiteBlockFactory> */
    use HasFactory;

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }
}
