<?php

namespace App\Support;

class GoogleCalendarUrl
{
    /** @var list<string>|null */
    private static ?array $timeZones = null;

    public static function from(string $source): ?string
    {
        $source = trim($source);
        if (! str_contains($source, '/calendar/embed?')
            || strlen($source) > 8192
            || preg_match('/[\x00-\x20\x7F\\\\<>]/', $source) === 1
            || preg_match('/%(?![a-fA-F0-9]{2})/', $source) === 1
            || preg_match('/[\x00-\x1F\x7F]/', rawurldecode($source)) === 1
            || preg_match('~\Ahttps://calendar\.google\.com(?::443)?/calendar/embed\?([^#]+)\z~i', $source, $match) !== 1) {
            return null;
        }
        $parameters = [];
        foreach (explode('&', $match[1]) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($key);
            $value = urldecode($value);
            if (preg_match('/\A[a-zA-Z0-9_-]+\z/', $key) !== 1 || array_key_exists($key, $parameters)) {
                return null;
            }
            $parameters[$key] = $value;
        }
        $id = $parameters['src'] ?? '';
        if (preg_match('/\A[\x21-\x7E]+\z/', $id) !== 1 || strpbrk($id, '<>\\') !== false) {
            return null;
        }
        $mode = $parameters['mode'] ?? 'AGENDA';
        if (! in_array($mode, ['MONTH', 'WEEK', 'AGENDA'], true)) {
            return null;
        }
        $query = ['src' => $id];
        if (array_key_exists('ctz', $parameters)) {
            if (! in_array(strtolower($parameters['ctz']), self::supportedTimeZones(), true)) {
                return null;
            }
            $query['ctz'] = $parameters['ctz'];
        }
        $query['mode'] = $mode;

        return 'https://calendar.google.com/calendar/embed?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return list<string> */
    private static function supportedTimeZones(): array
    {
        if (self::$timeZones === null) {
            $contents = file_get_contents(dirname(__DIR__, 2).'/resources/js/lib/google-calendar-time-zones.json');
            if ($contents === false) {
                throw new \RuntimeException('Calendar time-zone list is unavailable.');
            }
            $zones = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($zones)) {
                throw new \UnexpectedValueException('Calendar time-zone list must be an array.');
            }
            self::$timeZones = array_values(array_filter($zones, is_string(...)));
        }

        return self::$timeZones;
    }
}
