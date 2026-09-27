const errors = new Map([
    [400, 'invalid_request'],
    [403, 'forbidden'],
    [404, 'not_found'],
    [405, 'method_not_allowed'],
    [500, 'unavailable'],
    [503, 'unavailable'],
]);

function hostnameValid(host) {
    return (
        typeof host === 'string' &&
        host.length <= 253 &&
        host === host.trim() &&
        /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z](?:[a-z0-9-]{0,61}[a-z0-9])?$/.test(
            host,
        )
    );
}

function parseUrl(raw) {
    if (
        typeof raw !== 'string' ||
        /[^\x21-\x7e]/.test(raw) ||
        raw.includes('\\') ||
        /%(?![a-f\d]{2})/i.test(raw)
    ) {
        return null;
    }
    try {
        const url = new URL(raw);
        // Do not turn ambiguous authorities or traversal into an allowed target.
        if (
            url.href !== raw ||
            url.username ||
            url.password ||
            raw.includes('#') ||
            url.port ||
            !hostnameValid(url.hostname)
        ) {
            return null;
        }
        return url;
    } catch (error) {
        if (error instanceof TypeError) return null;
        throw error;
    }
}

function reply(request, status, data, extraHeaders = {}) {
    return new Response(
        request.method === 'HEAD' ? null : JSON.stringify(data),
        {
            status,
            headers: {
                'Content-Type': 'application/json',
                'Cache-Control': 'no-store',
                'X-Robots-Tag': 'noindex, nofollow',
                ...(status === 405 ? { Allow: 'GET, HEAD' } : {}),
                ...extraHeaders,
            },
        },
    );
}

function fail(request, status, error = errors.get(status)) {
    return reply(request, status, { error });
}

// The injected fetch seam keeps tests offline; production uses the native fetch.
export async function forwardRequest(
    request,
    env,
    fetchOrigin = globalThis.fetch,
) {
    const origin = parseUrl(env?.ORIGIN_ENDPOINT);
    if (
        !origin ||
        origin.protocol !== 'https:' ||
        origin.pathname !== '/_domain/request' ||
        origin.search ||
        env.ORIGIN_ENDPOINT.includes('?') ||
        !hostnameValid(env.PROOF_HOST) ||
        env.PROOF_HOST === origin.hostname ||
        typeof env.ORIGIN_SECRET !== 'string' ||
        env.ORIGIN_SECRET.length !== 64 ||
        !/^[a-f\d]{64}$/.test(env.ORIGIN_SECRET)
    ) {
        return fail(request, 503);
    }
    if (!['GET', 'HEAD'].includes(request.method)) return fail(request, 405);

    const original = parseUrl(request.url);
    if (!original || !['http:', 'https:'].includes(original.protocol))
        return fail(request, 400);
    if (original.hostname !== env.PROOF_HOST) return fail(request, 404);
    if (original.protocol === 'http:') {
        original.protocol = 'https:';
        return reply(request, 308, null, { Location: original.href });
    }

    const headers = new Headers({
        Authorization: `Bearer ${env.ORIGIN_SECRET}`,
        'X-Churchsite-Proxy-Version': '1',
        'X-Churchsite-Original-Url': original.href,
    });
    const accept = request.headers.get('Accept');
    if (accept) headers.set('Accept', accept);

    let upstream;
    try {
        upstream = await fetchOrigin(origin.href, {
            method: request.method,
            headers,
            redirect: 'manual',
            cache: 'no-store',
        });
    } catch {
        // Network failures must not expose diagnostic text or secret headers.
        return fail(request, 502, 'upstream_unavailable');
    }
    if (
        upstream.redirected ||
        (upstream.status !== 200 && !errors.has(upstream.status)) ||
        !/^application\/json(?:\s*;|$)/i.test(
            upstream.headers.get('Content-Type') ?? '',
        )
    ) {
        await upstream.body?.cancel().catch(() => undefined);
        return fail(request, 502, 'upstream_unavailable');
    }
    if (request.method === 'HEAD') {
        await upstream.body?.cancel().catch(() => undefined);
        return reply(request, upstream.status, null);
    }

    let data;
    try {
        data = await upstream.json();
    } catch {
        return fail(request, 502, 'upstream_unavailable');
    }
    // Reconstruct the small protocol response; never relay arbitrary origin HTML,
    // extra JSON fields, cookies, redirects, or infrastructure response headers.
    if (upstream.status === 200) {
        if (
            !data ||
            typeof data !== 'object' ||
            Object.keys(data).length !== 3 ||
            data.status !== 'ok' ||
            data.transport !== 'worker' ||
            data.hostname !== env.PROOF_HOST
        )
            return fail(request, 502, 'upstream_unavailable');
        return reply(request, 200, {
            status: 'ok',
            transport: 'worker',
            hostname: env.PROOF_HOST,
        });
    }
    if (
        !data ||
        typeof data !== 'object' ||
        Object.keys(data).length !== 1 ||
        data.error !== errors.get(upstream.status)
    ) {
        return fail(request, 502, 'upstream_unavailable');
    }
    return fail(request, upstream.status);
}

export default {
    fetch(request, env) {
        return forwardRequest(request, env);
    },
};
