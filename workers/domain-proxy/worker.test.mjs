import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFile } from 'node:fs/promises';
import worker, { forwardRequest } from './worker.mjs';

const env = {
    ORIGIN_ENDPOINT: 'https://churchsite.app/_domain/request',
    PROOF_HOST: 'test.timjossund.com',
    ORIGIN_SECRET: 'a'.repeat(64), // Test-only fixture, never a deployment secret.
};
const proof = { status: 'ok', transport: 'worker', hostname: env.PROOF_HOST };
const json = (body = proof, status = 200, headers = {}) =>
    new Response(JSON.stringify(body), {
        status,
        headers: {
            'Content-Type': 'application/json; charset=utf-8',
            ...headers,
        },
    });
const request = (path = '/up', options = {}) =>
    new Request(`https://${env.PROOF_HOST}${path}`, options);
const offline = () => {
    assert.fail('Origin must not be called');
};
async function errorIs(response, status, error) {
    assert.equal(response.status, status);
    assert.deepEqual(await response.json(), { error });
    assert.equal(response.headers.get('Cache-Control'), 'no-store');
    assert.equal(response.headers.get('Set-Cookie'), null);
}

await test('fixed upstream and fresh headers preserve original URL without visitor credentials', async () => {
    let calls = 0;
    const incoming = request('/about?x=one%20two&next=https://evil.example', {
        headers: {
            Accept: 'text/html',
            Cookie: 'session=private',
            Authorization: 'Bearer visitor',
            'X-Churchsite-Original-Url': 'https://evil.example/up',
            'X-Churchsite-Proxy-Version': 'evil',
            Forwarded: 'host=evil.example',
            'X-Forwarded-Host': 'evil.example',
            'X-Arbitrary': 'private',
        },
    });
    const response = await forwardRequest(
        incoming,
        env,
        async (url, options) => {
            calls++;
            assert.equal(url, env.ORIGIN_ENDPOINT);
            assert.equal(options.method, 'GET');
            assert.equal(options.redirect, 'manual');
            assert.equal(options.cache, 'no-store');
            assert.equal(options.body, undefined);
            assert.deepEqual(Object.fromEntries(options.headers), {
                accept: 'text/html',
                authorization: `Bearer ${env.ORIGIN_SECRET}`,
                'x-churchsite-original-url': incoming.url,
                'x-churchsite-proxy-version': '1',
            });
            return json({ error: 'not_found' }, 404);
        },
    );
    assert.equal(calls, 1);
    await errorIs(response, 404, 'not_found');
});

await test('success relays only validated JSON and safe response headers', async () => {
    const response = await forwardRequest(request(), env, async () =>
        json(proof, 200, {
            'Set-Cookie': 'session=private',
            Location: 'https://evil.example',
            'X-Debug': 'private',
        }),
    );
    assert.equal(response.status, 200);
    assert.deepEqual(await response.json(), proof);
    assert.equal(response.headers.get('Set-Cookie'), null);
    assert.equal(response.headers.get('Location'), null);
    assert.equal(response.headers.get('X-Debug'), null);
    assert.equal(response.headers.get('X-Robots-Tag'), 'noindex, nofollow');
});

for (const [key, value] of [
    ['ORIGIN_ENDPOINT', undefined],
    ['ORIGIN_ENDPOINT', 'http://churchsite.app/_domain/request'],
    ['ORIGIN_ENDPOINT', 'https://churchsite.app/login'],
    ['ORIGIN_ENDPOINT', 'https://churchsite.app/_domain/request?'],
    ['ORIGIN_ENDPOINT', 'https://user:pass@churchsite.app/_domain/request'],
    ['ORIGIN_ENDPOINT', 'https://churchsite.app/_domain/request#x'],
    ['ORIGIN_ENDPOINT', 'https://churchsite.app:8443/_domain/request'],
    ['ORIGIN_SECRET', 'a'.repeat(64) + '\n'],
    ['PROOF_HOST', 'test.timjossund.com\n'],
    ['ORIGIN_SECRET', undefined],
    ['ORIGIN_SECRET', 'short'],
    ['ORIGIN_SECRET', 'A'.repeat(64)],
    ['PROOF_HOST', undefined],
    ['PROOF_HOST', 'churchsite.app'],
    ['PROOF_HOST', '127.0.0.1'],
])
    await test(`fails closed for invalid config ${key}=${value}`, async () => {
        await errorIs(
            await forwardRequest(request(), { ...env, [key]: value }, offline),
            503,
            'unavailable',
        );
    });

await test('missing environment fails closed', async () => {
    await errorIs(
        await forwardRequest(request(), undefined, offline),
        503,
        'unavailable',
    );
});

for (const method of ['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'])
    await test(`rejects ${method}`, async () => {
        const response = await forwardRequest(
            request('/up', { method }),
            env,
            offline,
        );
        assert.equal(response.headers.get('Allow'), 'GET, HEAD');
        await errorIs(response, 405, 'method_not_allowed');
    });

for (const url of ['https://other.example/up', 'https://churchsite.app/up'])
    await test(`rejects unapproved host ${url}`, async () => {
        await errorIs(
            await forwardRequest(new Request(url), env, offline),
            404,
            'not_found',
        );
    });

for (const url of [
    'ftp://test.timjossund.com/up',
    'https://test.timjossund.com:8443/up',
    'https://user@test.timjossund.com/up',
    'https://test.timjossund.com/up#x',
    'https://test.timjossund.com/x/../up',
    'https://test.timjossund.com/%2e/up',
    'https://test.timjossund.com\\@evil.example/up',
    'not a URL',
    'https://test.timjossund.com/up%ZZ',
    'https://test.timjossund.com/up\n',
])
    await test(`rejects raw malformed or ambiguous URL ${JSON.stringify(url)}`, async () => {
        // A raw boundary stub preserves cases the Node Request constructor normalizes.
        await errorIs(
            await forwardRequest(
                { url, method: 'GET', headers: new Headers() },
                env,
                offline,
            ),
            400,
            'invalid_request',
        );
    });

await test('HTTP redirects safely on the approved hostname and preserves path and query', async () => {
    const response = await forwardRequest(
        new Request('http://test.timjossund.com/about?a=1'),
        env,
        offline,
    );
    assert.equal(response.status, 308);
    assert.equal(
        response.headers.get('Location'),
        'https://test.timjossund.com/about?a=1',
    );
    assert.equal(response.headers.get('Cache-Control'), 'no-store');
});

for (const status of [400, 403, 404, 405, 500, 503])
    await test(`relays only the expected ${status} error`, async () => {
        const code = {
            400: 'invalid_request',
            403: 'forbidden',
            404: 'not_found',
            405: 'method_not_allowed',
            500: 'unavailable',
            503: 'unavailable',
        }[status];
        await errorIs(
            await forwardRequest(request(), env, async () =>
                json({ error: code }, status),
            ),
            status,
            code,
        );
    });

for (const [label, upstream] of [
    [
        'redirect',
        () =>
            new Response(null, {
                status: 302,
                headers: { Location: 'https://evil.example' },
            }),
    ],
    [
        'HTML',
        () =>
            new Response('<html>private</html>', {
                headers: { 'Content-Type': 'text/html' },
            }),
    ],
    [
        'invalid JSON',
        () =>
            new Response('private', {
                headers: { 'Content-Type': 'application/json' },
            }),
    ],
    ['wrong hostname', () => json({ ...proof, hostname: 'other.example' })],
    ['extra fields', () => json({ ...proof, secret: 'private' })],
    ['unexpected status', () => json(proof, 201)],
    ['wrong error', () => json({ error: 'private diagnostic' }, 403)],
])
    await test(`hides upstream ${String(label)}`, async () => {
        let calls = 0;
        await errorIs(
            await forwardRequest(request(), env, async () => {
                calls++;
                return upstream();
            }),
            502,
            'upstream_unavailable',
        );
        assert.equal(calls, 1);
    });

await test('hides network exception details', async () => {
    await errorIs(
        await forwardRequest(request(), env, async () => {
            throw new Error(`private ${env.ORIGIN_SECRET}`);
        }),
        502,
        'upstream_unavailable',
    );
});

for (const status of [200, 403, 404, 500])
    await test(`HEAD ${status} preserves status without a body or cookies`, async () => {
        const response = await forwardRequest(
            request('/up', { method: 'HEAD' }),
            env,
            async (_, options) => {
                assert.equal(options.method, 'HEAD');
                return new Response(null, {
                    status,
                    headers: {
                        'Content-Type': 'application/json',
                        'Set-Cookie': 'secret',
                    },
                });
            },
        );
        assert.equal(response.status, status);
        assert.equal(await response.text(), '');
        assert.equal(response.headers.get('Set-Cookie'), null);
    });

await test('HEAD local errors and network errors are bodyless', async () => {
    for (const response of [
        await forwardRequest(request('/up', { method: 'HEAD' }), {}, offline),
        await forwardRequest(
            request('/up', { method: 'HEAD' }),
            env,
            async () => {
                throw new Error('private');
            },
        ),
    ]) {
        assert.equal(await response.text(), '');
        assert.equal(response.headers.get('Cache-Control'), 'no-store');
    }
});

await test('production export uses the same guarded handler', async () => {
    await errorIs(await worker.fetch(request(), {}), 503, 'unavailable');
});

await test('encoded paths reach the receiver unchanged rather than becoming the proof path', async () => {
    await errorIs(
        await forwardRequest(request('/%75p'), env, async (_, options) => {
            assert.equal(
                options.headers.get('X-Churchsite-Original-Url'),
                'https://test.timjossund.com/%75p',
            );
            return json({ error: 'not_found' }, 404);
        }),
        404,
        'not_found',
    );
});

await test('HEAD HTTP redirect is bodyless', async () => {
    const response = await forwardRequest(
        new Request('http://test.timjossund.com/up', { method: 'HEAD' }),
        env,
        offline,
    );
    assert.equal(response.status, 308);
    assert.equal(await response.text(), '');
    assert.equal(
        response.headers.get('Location'),
        'https://test.timjossund.com/up',
    );
});

const protocol = JSON.parse(
    await readFile(
        new URL('../../tests/Fixtures/domain-proxy-v1.json', import.meta.url),
        'utf8',
    ),
);
for (const fixture of protocol.cases) {
    await test(`shared protocol: ${String(fixture.name)}`, async () => {
        const response = await forwardRequest(
            {
                method: fixture.method,
                url: fixture.url,
                headers: new Headers(),
            },
            {
                ORIGIN_ENDPOINT: protocol.origin_endpoint,
                ORIGIN_SECRET: protocol.secret,
                PROOF_HOST: protocol.proof_host,
            },
            async (url, options) => {
                assert.equal(url, protocol.origin_endpoint);
                assert.equal(
                    options.headers.get('Authorization'),
                    `Bearer ${String(protocol.secret)}`,
                );
                assert.equal(
                    options.headers.get('X-Churchsite-Proxy-Version'),
                    protocol.version,
                );
                assert.equal(
                    options.headers.get('X-Churchsite-Original-Url'),
                    fixture.url,
                );
                assert.equal(options.method, fixture.method);
                return new Response(
                    fixture.method === 'HEAD'
                        ? null
                        : JSON.stringify(fixture.body),
                    {
                        status: fixture.status,
                        headers: { 'Content-Type': 'application/json' },
                    },
                );
            },
        );
        assert.equal(response.status, fixture.status);
        if (fixture.method === 'HEAD') assert.equal(await response.text(), '');
        else assert.deepEqual(await response.json(), fixture.body);
        assert.equal(response.headers.get('Cache-Control'), 'no-store');
        assert.equal(response.headers.get('Set-Cookie'), null);
    });
}
