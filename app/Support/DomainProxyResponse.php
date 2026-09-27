<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DomainProxyResponse
{
    /** @param array<string, string> $data */
    public static function make(Request $request, array $data, int $status = 200): JsonResponse
    {
        $response = response()->json($data, $status, [
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
        if ($status === 405) {
            $response->headers->set('Allow', 'GET, HEAD');
        }

        return $response->prepare($request);
    }

    public static function error(Request $request, int $status): JsonResponse
    {
        return self::make($request, ['error' => match ($status) {
            400 => 'invalid_request',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            default => 'unavailable',
        }], $status);
    }
}
