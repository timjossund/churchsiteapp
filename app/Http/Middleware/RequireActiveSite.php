<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSite
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route('site') !== null && ! $request->routeIs('sites.destroy')) {
            $request->user()->sites()->whereNull('deletion_requested_at')
                ->whereKey($request->route('site'))->firstOrFail();
        }

        return $next($request);
    }
}
