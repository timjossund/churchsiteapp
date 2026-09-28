<?php

use App\Support\SiteAppearance;

test('custom accents have readable text actions and accent section pairs', function (string $theme, array $backgrounds, string $color) {
    $palette = SiteAppearance::colors(['accent_color' => $color], $theme);
    // Independent luminance calculation checks the resulting colors, not the selected shade.
    $luminance = function (string $hex): float {
        $rgb = sscanf($hex, '#%02x%02x%02x');
        $sum = 0;
        foreach ([0.2126, 0.7152, 0.0722] as $index => $weight) {
            $channel = $rgb[$index] / 255;
            $sum += ($channel <= 0.04045 ? $channel / 12.92 : pow(($channel + 0.055) / 1.055, 2.4)) * $weight;
        }

        return $sum;
    };
    $ratio = fn ($a, $b) => (max($luminance($a), $luminance($b)) + 0.05) / (min($luminance($a), $luminance($b)) + 0.05);
    foreach ($backgrounds as $background) {
        expect($ratio($palette['--site-preview-accent'], $background))->toBeGreaterThanOrEqual(4.5);
    }
    expect($ratio($palette['--site-preview-action'], $palette['--site-preview-action-ink']))->toBeGreaterThanOrEqual(4.5);
    expect(SiteAppearance::colors(['accent_color' => $color], $theme))->toBe($palette);
})->with([
    ['warm', ['#fffaf0', '#f4e7d7']],
    ['clean', ['#f8fafc', '#e2e8f0']],
    ['bold', ['#211c36', '#392d54']],
])->with(['#000000', '#ffffff', '#ff0000', '#00ff00', '#0000ff', '#808080', '#ffeecc']);

test('missing and unsafe accents preserve theme colors', function () {
    foreach ([null, [], ['accent_color' => 'url(evil)'], ['accent_color' => []]] as $appearance) {
        expect(SiteAppearance::colors($appearance, 'warm'))->toBe([]);
    }
});
