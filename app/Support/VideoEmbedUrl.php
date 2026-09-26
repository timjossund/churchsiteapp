<?php

namespace App\Support;

class VideoEmbedUrl
{
    public static function from(string $source): ?string
    {
        $source = trim($source);
        if (preg_match('/[\x00-\x1F\x7F]/', $source) === 1) {
            return null;
        }

        $parts = parse_url($source);
        if ($parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            return null;
        }

        $host = strtolower($parts['host']);
        $path = $parts['path'] ?? '';
        $query = self::parseQuery($parts['query'] ?? '');
        if ($query === null) {
            return null;
        }

        if (in_array($host, ['youtube.com', 'www.youtube.com'], true) && $path === '/watch') {
            $videoId = $query['v'] ?? null;

            return ! array_key_exists('list', $query)
                && is_string($videoId)
                && preg_match('/\A[A-Za-z0-9_-]{11}\z/', $videoId) === 1
                ? "https://www.youtube-nocookie.com/embed/{$videoId}"
                : null;
        }

        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)
            && ! array_key_exists('list', $query)
            && preg_match('/\A\/([A-Za-z0-9_-]{11})\z/', $path, $match) === 1) {
            return "https://www.youtube-nocookie.com/embed/{$match[1]}";
        }

        if (in_array($host, ['vimeo.com', 'www.vimeo.com'], true)
            && preg_match('/\A\/([0-9]+)\z/', $path, $match) === 1) {
            return "https://player.vimeo.com/video/{$match[1]}";
        }

        return null;
    }

    /** @return array<string, string>|null */
    private static function parseQuery(string $query): ?array
    {
        if ($query === '') {
            return [];
        }

        $parameters = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                return null;
            }

            [$encodedKey, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($encodedKey);
            if (preg_match('/\A[A-Za-z0-9_-]+\z/', $key) !== 1 || array_key_exists($key, $parameters)) {
                return null;
            }

            $parameters[$key] = urldecode($value);
        }

        return $parameters;
    }
}
