<?php

namespace App\Http\Middleware;

use App\Support\EmbedFramePolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\View;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);
        // A document policy survives Inertia navigation. Cover its entry pages too,
        // otherwise opening the editor from another app page would bypass this policy.
        $isAppDocument = $response instanceof \Illuminate\Http\Response
            && $response->original instanceof View
            && $response->original->getName() === $this->rootView;
        if ($isAppDocument || $response->headers->has('X-Inertia')) {
            EmbedFramePolicy::apply($response);
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        // Builds must not reload an editor using Vite's development assets.
        if (Vite::isRunningHot()) {
            return null;
        }

        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
