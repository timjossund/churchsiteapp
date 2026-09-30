<?php

namespace App\Support;

class OpenStreetMapUrl
{
    /** @return array{embed_url: string, location_url: string}|null */
    public static function from(string $source): ?array
    {
        $source = trim($source);
        if (preg_match('/[\x00-\x20\x7F\\\\<>]/', $source) === 1
            || preg_match('/[\x00-\x1F\x7F]/', rawurldecode($source)) === 1
            || preg_match('/%(?![a-fA-F0-9]{2})/', $source) === 1) {
            return null;
        }

        $parts = parse_url($source);
        if ($parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), ['openstreetmap.org', 'www.openstreetmap.org'], true)
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            return null;
        }

        $parameters = [];
        foreach (explode('&', $parts['query'] ?? '') as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            $value = urldecode($value);
            if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $key) !== 1
                || array_key_exists($key, $parameters)
                || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                return null;
            }
            $parameters[$key] = $value;
        }

        $latitude = self::coordinate($parameters['mlat'] ?? '', 90);
        $longitude = self::coordinate($parameters['mlon'] ?? '', 180);
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $marker = self::number($latitude).','.self::number($longitude);
        // A fixed neighborhood view keeps the preview and publication consistent.
        $west = max(-180, min(179.98, $longitude - 0.01));
        $south = max(-90, min(89.99, $latitude - 0.005));
        $bbox = implode(',', array_map(self::number(...), [$west, $south, $west + 0.02, $south + 0.01]));

        return [
            'embed_url' => 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
                'bbox' => $bbox, 'layer' => 'mapnik', 'marker' => $marker,
            ], '', '&', PHP_QUERY_RFC3986),
            'location_url' => 'https://www.openstreetmap.org/?'.http_build_query([
                'mlat' => self::number($latitude), 'mlon' => self::number($longitude),
            ], '', '&', PHP_QUERY_RFC3986).'#map=16/'.self::number($latitude).'/'.self::number($longitude),
        ];
    }

    private static function coordinate(string $value, int $limit): ?float
    {
        if (preg_match('/\A[+-]?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)\z/', $value) !== 1) {
            return null;
        }
        $number = (float) $value;

        return is_finite($number) && abs($number) <= $limit ? $number : null;
    }

    private static function number(float $number): string
    {
        $formatted = rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }
}
