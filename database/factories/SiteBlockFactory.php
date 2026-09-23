<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SiteBlock> */
class SiteBlockFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'type' => 'plain_text',
            'position' => 0,
            'content' => ['body' => ''],
        ];
    }
}
