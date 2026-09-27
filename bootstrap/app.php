<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequirePlatformHost;
use App\Support\DomainProxyResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => require __DIR__.'/../routes/domain-proxy.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequirePlatformHost::class);
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request): bool => $request->isMethod('PATCH')
                && preg_match('#^sites/[0-9]+/(?:pages/[0-9]+/)?blocks/[0-9]+$#', $request->path()) === 1,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($request->is('_domain/*')) {
                $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

                return DomainProxyResponse::error($request, $status);
            }
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
