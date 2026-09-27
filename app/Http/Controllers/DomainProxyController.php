<?php

namespace App\Http\Controllers;

use App\Support\DomainProxyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DomainProxyController extends Controller
{
    public function __invoke(Request $request): JsonResponse
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
        if ($request->header('X-Churchsite-Proxy-Version') !== '1'
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
        if ($host !== $proofHost || ($parts['path'] ?? null) !== '/up'
            || array_key_exists('query', $parts)) {
            return DomainProxyResponse::error($request, 404);
        }

        return DomainProxyResponse::make($request, [
            'status' => 'ok', 'transport' => 'worker', 'hostname' => $host,
        ]);
    }

    private function validHostname(string $host): bool
    {
        return strlen($host) <= 253
            && preg_match('/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?\z/', $host) === 1
            && filter_var($host, FILTER_VALIDATE_IP) === false;
    }
}
