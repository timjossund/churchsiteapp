<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

final class EmbedFramePolicy
{
    public const POLICY = 'frame-src https://calendar.google.com https://www.youtube-nocookie.com https://player.vimeo.com https://www.openstreetmap.org';

    public static function apply(Response $response): void
    {
        // Multiple CSP policies intersect; retain existing restrictions rather than replace them.
        $response->headers->set('Content-Security-Policy', self::POLICY, false);

    }
}
