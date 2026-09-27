<?php

namespace App\Http\Middleware;

use App\Support\DomainProxyResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePlatformHost
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $platform = parse_url((string) config('app.url'), PHP_URL_HOST);
        // Inspect the actual HTTP authority, never a trusted forwarded-host override.
        $authority = strtolower($request->headers->get('host', ''));
        if (! is_string($platform)
            || preg_match('/\A'.preg_quote(strtolower($platform), '/').'(?::[0-9]+)?\z/', $authority) !== 1) {
            return DomainProxyResponse::error($request, 404);
        }

        return $next($request);
    }
}
