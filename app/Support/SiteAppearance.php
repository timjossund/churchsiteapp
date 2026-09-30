<?php

namespace App\Support;

final class SiteAppearance
{
    /** @return array{font_pairing: string, button_shape: string, accent_color: string|null, show_site_title: bool} */
    public static function normalize(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'show_site_title' => ($value['show_site_title'] ?? true) !== false,
            'font_pairing' => in_array($value['font_pairing'] ?? null, ['theme', 'traditional', 'modern', 'editorial', 'classy'], true) ? $value['font_pairing'] : 'theme',
            'button_shape' => in_array($value['button_shape'] ?? null, ['theme', 'rounded', 'pill', 'square'], true) ? $value['button_shape'] : 'theme',
            'accent_color' => is_string($value['accent_color'] ?? null) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $value['accent_color']) === 1 ? strtolower($value['accent_color']) : null,
        ];
    }

    /** @return array<string, string> */
    public static function colors(mixed $appearance, string $theme): array
    {
        $color = self::normalize($appearance)['accent_color'];
        if ($color === null) {
            return [];
        }
        $backgrounds = match ($theme) {
            'clean' => ['#f8fafc', '#e2e8f0'],
            'bold' => ['#211c36', '#392d54'],
            default => ['#fffaf0', '#f4e7d7'],
        };
        // Choose the closest tint or shade that works on both ordinary section surfaces.
        for ($step = 0; $step <= 255; $step++) {
            foreach ([0, 255] as $target) {
                $rgb = self::rgb($color);
                $candidate = sprintf('#%02x%02x%02x', ...array_map(
                    fn (int $channel): int => (int) round($channel + ($target - $channel) * $step / 255), $rgb));
                if (min(array_map(fn (string $background): float => self::contrast($candidate, $background), $backgrounds)) >= 4.5) {
                    $ink = self::contrast($candidate, '#ffffff') >= 4.5 ? '#ffffff' : '#000000';

                    return [
                        '--site-preview-accent' => $candidate,
                        '--site-preview-action' => $candidate,
                        '--site-preview-action-ink' => $ink,
                    ];
                }
            }
        }

        return [];
    }

    /** @return list<int> */
    private static function rgb(string $color): array
    {
        return array_map(fn (string $hex): int => (int) hexdec($hex), str_split(substr($color, 1), 2));
    }

    private static function luminance(string $color): float
    {
        $linear = array_map(function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::rgb($color));

        return $linear[0] * 0.2126 + $linear[1] * 0.7152 + $linear[2] * 0.0722;
    }

    private static function contrast(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }
}
