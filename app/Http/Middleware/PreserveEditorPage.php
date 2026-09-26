<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PreserveEditorPage
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->query->has('editor_page') || $request->route('site') === null) {
            return $next($request);
        }

        $pageId = $request->query('editor_page');
        if (! is_string($pageId) || ! ctype_digit($pageId)) {
            throw ValidationException::withMessages(['editor_page' => 'Choose a valid editor page.']);
        }
        $site = $request->user()->sites()->whereKey($request->route('site'))->firstOrFail();
        $page = $site->pages()->whereKey($pageId)->firstOrFail();
        $response = $next($request);

        if ($response instanceof RedirectResponse
            && $response->getTargetUrl() === route('sites.show', $site)
            && $site->pages()->whereKey($page->id)->exists()) {
            $response->setTargetUrl(route('sites.pages.show', [$site, $page]));
        }

        return $response;
    }
}
