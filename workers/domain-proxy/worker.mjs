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
    const customer = original.hostname !== env.PROOF_HOST;
    if (
        customer &&
        (env.CUSTOMER_DOMAINS_ENABLED !== 'true' ||
            !original.hostname.startsWith('www.') ||
            original.hostname === origin.hostname ||
            original.hostname.endsWith(`.${origin.hostname}`) ||
            original.hostname === 'churchsite.app' ||
            original.hostname.endsWith('.churchsite.app'))
    )
        return fail(request, 404);
    if (original.protocol === 'http:') {
        original.protocol = 'https:';
        return reply(request, 308, null, { Location: original.href });
    }

    if (customer) {
        const reserved = new Set([
            'editor',
            'checkout',
            'webhook',
            'passkey',
            'passkeys',
            'login',
            'logout',
            'register',
            'dashboard',
            'sites',
            'settings',
            'profile',
            'billing',
            'stripe',
            's',
            'up',
            'api',
            'admin',
            'password',
            'forgot-password',
            'reset-password',
            'confirm-password',
            'email',
            'verify-email',
            'two-factor-challenge',
            'user',
            'storage',
            'build',
        ]);
        const path = original.pathname;
        if (
            reserved.has(path.slice(1)) ||
            !(
                path === '/' ||
                (path.length <= 101 &&
                    /^\/[a-z0-9]+(?:-[a-z0-9]+)*$/.test(path)) ||
                /^\/_media\/[1-9][0-9]*$/.test(path) ||
                /^\/build\/assets\/[a-zA-Z0-9_-]+\.(css|js|woff2?|ttf)$/.test(
                    path,
                )
            )
        )
            return fail(request, 404);
    }

    const headers = new Headers({
        Authorization: `Bearer ${env.ORIGIN_SECRET}`,
        'X-Churchsite-Proxy-Version': customer ? '2' : '1',
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
    if (customer && upstream.status === 200) {
        const type = (upstream.headers.get('Content-Type') ?? '')
            .split(';')[0]
            .trim()
            .toLowerCase();
        const kind = upstream.headers.get('X-Churchsite-Content');
        const path = original.pathname;
        const expected = /^\/_media\/[1-9][0-9]*$/.test(path)
            ? 'media'
            : /^\/build\/assets\/[a-zA-Z0-9_-]+\.(css|js|woff2?|ttf)$/.test(
                    path,
                )
              ? 'asset'
              : path === '/' || /^\/[a-z0-9]+(?:-[a-z0-9]+)*$/.test(path)
                ? 'html'
                : null;
        const assetTypes = {
            css: 'text/css',
            js: 'application/javascript',
            woff: 'font/woff',
            woff2: 'font/woff2',
            ttf: 'font/ttf',
        };
        const types = {
            html: ['text/html'],
            media: ['image/jpeg', 'image/png'],
            asset: [
                'text/css',
                'application/javascript',
                'font/woff',
                'font/woff2',
                'font/ttf',
            ],
        };
        if (
            upstream.redirected ||
            upstream.headers.has('Location') ||
            upstream.headers.has('Set-Cookie') ||
            kind !== expected ||
            !types[kind]?.includes(type) ||
            (kind === 'asset' && assetTypes[path.split('.').at(-1)] !== type) ||
            upstream.headers.get('X-Churchsite-Response-Version') !== '2' ||
            upstream.headers.get('X-Churchsite-Hostname') !== original.hostname
        ) {
            await upstream.body?.cancel().catch(() => undefined);
            return fail(request, 502, 'upstream_unavailable');
        }
        const safeHeaders = new Headers({
            'Content-Type': type,
            'Cache-Control': 'no-store',
            'X-Content-Type-Options': 'nosniff',
        });
        if (kind === 'html' && upstream.headers.has('Content-Security-Policy'))
            safeHeaders.set(
                'Content-Security-Policy',
                upstream.headers.get('Content-Security-Policy'),
            );
        if (kind !== 'html')
            safeHeaders.set('X-Robots-Tag', 'noindex, nofollow');
        if (request.method === 'HEAD')
            await upstream.body?.cancel().catch(() => undefined);
        return new Response(request.method === 'HEAD' ? null : upstream.body, {
            status: 200,
            headers: safeHeaders,
        });
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
