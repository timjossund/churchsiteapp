<?php

namespace App\Http\Controllers;

use App\Models\CustomHostname;
use App\Rules\CustomerHostname;
use App\Support\CustomerPagePath;
use App\Support\DomainProxyResponse;
use App\Support\PublishedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class DomainProxyController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $secret = config('domain-proxy.secret');
        $proofHost = config('domain-proxy.proof_host');
        if (config('domain-proxy.enabled') !== true
            || ! is_string($secret) || preg_match('/\A[a-f0-9]{64}\z/', $secret) !== 1
            || ! is_string($proofHost) || ! $this->validHostname($proofHost)
            || $proofHost === parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return DomainProxyResponse::error($request, 404);
        }

        // Exact syntax also rejects combined/duplicated Authorization values.
        $authorization = $request->header('Authorization');
        if (! is_string($authorization)
            || ! hash_equals('Bearer '.$secret, $authorization)) {
            return DomainProxyResponse::error($request, 403);
        }
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return DomainProxyResponse::error($request, 405);
        }

        $url = $request->header('X-Churchsite-Original-Url');
        $version = $request->header('X-Churchsite-Proxy-Version');
        if (! in_array($version, ['1', '2'], true)
            || ! is_string($url)
            || preg_match('/[\x00-\x20\x7f-\xff\\\\]/', $url) === 1) {
            return DomainProxyResponse::error($request, 400);
        }
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || ! is_string($host) || ! $this->validHostname($host)
            || $host === strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST))
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)
            || preg_match('/\Ahttps:\/\/'.preg_quote($host, '/').'(?::443)?\//', $url) !== 1
            || preg_match('/%(?![a-fA-F0-9]{2})/', $url) === 1) {
            return DomainProxyResponse::error($request, 400);
        }
        $path = $parts['path'] ?? '';
        if ($version === '1') {
            if ($host !== $proofHost || $path !== '/up' || array_key_exists('query', $parts)) {
                return DomainProxyResponse::error($request, 404);
            }

            return DomainProxyResponse::make($request, [
                'status' => 'ok', 'transport' => 'worker', 'hostname' => $host,
            ]);
        }
        if ($host === $proofHost) {
            return DomainProxyResponse::error($request, 400);
        }
        if (config('customer-domains.enabled') !== true
            || Validator::make(['hostname' => $host], ['hostname' => [new CustomerHostname]])->fails()) {
            return DomainProxyResponse::error($request, 404);
        }
        $domain = CustomHostname::query()->where('hostname', $host)->first();
        if ($domain === null || ! $domain->isReadyToServe() || $domain->site->published_at === null) {
            return DomainProxyResponse::error($request, 404);
        }
        $renderer = app(PublishedSiteController::class);
        // Validate the frozen publication for every response, including its asset and media requests.
        $renderer->validatePublication($domain->site);
        if (preg_match('#\A/_media/([1-9][0-9]*)\z#', $path, $matches) === 1) {
            $response = $renderer->siteMedia($domain->site, (int) $matches[1]);
            $kind = 'media';
        } elseif (str_starts_with($path, '/build/assets/')) {
            $response = app(PublishedAssets::class)->response($path);
            $kind = 'asset';
        } elseif ($path === '/' || (str_starts_with($path, '/') && CustomerPagePath::valid(substr($path, 1)))) {
            $response = $renderer->renderSite($domain->site, $path === '/' ? null : substr($path, 1), $host);
            $kind = 'html';
        } else {
            return DomainProxyResponse::error($request, 404);
        }
        $response->headers->set('X-Churchsite-Content', $kind);
        $response->headers->set('X-Churchsite-Hostname', $host);
        $response->headers->set('X-Churchsite-Response-Version', '2');
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($request->isMethod('HEAD')) {
            return (new Response('', $response->getStatusCode(), $response->headers->all()))->prepare($request);
        }

        return $response;
    }

    private function validHostname(string $host): bool
    {
        return strlen($host) <= 253
            && preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $host) === 1
            && filter_var($host, FILTER_VALIDATE_IP) === false;
    }
}
