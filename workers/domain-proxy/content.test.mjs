import assert from 'node:assert/strict';
import { test } from 'node:test';
import { readFile } from 'node:fs/promises';
import { forwardRequest } from './worker.mjs';
const env = {
    ORIGIN_ENDPOINT: 'https://churchsite.app/_domain/request',
    PROOF_HOST: 'test.timjossund.com',
    ORIGIN_SECRET: 'a'.repeat(64),
    CUSTOMER_DOMAINS_ENABLED: 'true',
};
const url = 'https://www.example.org';
const content = (
    kind = 'html',
    type = 'text/html',
    headers = {},
    status = 200,
) =>
    new Response('published body', {
        status,
        headers: {
            'Content-Type': type,
            'X-Churchsite-Content': kind,
            'X-Churchsite-Response-Version': '2',
            'X-Churchsite-Hostname': 'www.example.org',
            ...headers,
        },
    });
const offline = () => assert.fail('Must not contact origin');
for (const [path, kind, type] of [
    ['/', 'html', 'text/html'],
    ['/about', 'html', 'text/html'],
    ['/_media/12', 'media', 'image/png'],
    ['/build/assets/published-abc.js', 'asset', 'application/javascript'],
    ['/build/assets/font-abc.woff2', 'asset', 'font/woff2'],
    ['/build/assets/style-abc.css', 'asset', 'text/css'],
]) {
    await test(`content ${path} is stateless and HEAD has matching headers`, async () => {
        for (const method of ['GET', 'HEAD']) {
            const response = await forwardRequest(
                new Request(url + path, {
                    method,
                    headers: {
                        Cookie: 'private',
                        Authorization: 'forged',
                        'X-Churchsite-Original-Url': 'https://evil.example/',
                    },
                }),
                env,
                async (target, options) => {
                    assert.equal(target, env.ORIGIN_ENDPOINT);
                    assert.equal(options.headers.get('Cookie'), null);
                    assert.equal(
                        options.headers.get('Authorization'),
                        'Bearer ' + env.ORIGIN_SECRET,
                    );
                    assert.equal(
                        options.headers.get('X-Churchsite-Original-Url'),
                        url + path,
                    );
                    assert.equal(
                        options.headers.get('X-Churchsite-Proxy-Version'),
                        '2',
                    );
                    return content(kind, type);
                },
            );
            assert.equal(response.status, 200);
            assert.equal(response.headers.get('Content-Type'), type);
            assert.equal(response.headers.get('Cache-Control'), 'no-store');
            assert.equal(response.headers.get('Set-Cookie'), null);
            assert.equal(response.headers.get('X-Churchsite-Hostname'), null);
            assert.equal(
                response.headers.get('X-Robots-Tag'),
                kind === 'html' ? null : 'noindex, nofollow',
            );
            assert.equal(
                await response.text(),
                method === 'HEAD' ? '' : 'published body',
            );
        }
    });
}
const fixture = JSON.parse(
    await readFile(
        new URL('../../tests/Fixtures/domain-proxy-v2.json', import.meta.url),
    ),
);
for (const path of fixture.denied_paths) {
    await test(`shared v2 denial ${path}`, async () => {
        const response = await forwardRequest(
            new Request(url + path),
            env,
            offline,
        );
        assert.equal(response.status, 404);
    });
}
for (const headers of [
    { 'X-Churchsite-Content': 'proof' },
    { 'X-Churchsite-Response-Version': '1' },
    { 'X-Churchsite-Hostname': 'www.other.org' },
    { 'Content-Type': 'application/json' },
    { 'Set-Cookie': 'private' },
    { Location: '/login' },
]) {
    await test(`rejects untrusted content contract ${JSON.stringify(headers)}`, async () => {
        const response = await forwardRequest(
            new Request(url + '/'),
            env,
            async () => content('html', 'text/html', headers),
        );
        assert.equal(response.status, 502);
        assert.equal(response.headers.get('Set-Cookie'), null);
    });
}
await test('customer content is opt-in and platform/origin hosts cannot recurse', async () => {
    assert.equal(
        (
            await forwardRequest(
                new Request(url + '/'),
                { ...env, CUSTOMER_DOMAINS_ENABLED: undefined },
                offline,
            )
        ).status,
        404,
    );
    for (const host of [
        'churchsite.app',
        'www.churchsite.app',
        'www.origin.churchsite.app',
        'www.churchsite.app.evil.churchsite.app',
    ]) {
        assert.equal(
            (
                await forwardRequest(
                    new Request(`https://${host}/`),
                    env,
                    offline,
                )
            ).status,
            404,
        );
    }
});
await test('media and assets cannot masquerade as HTML or mismatched asset types', async () => {
    for (const [path, kind, type] of [
        ['/_media/1', 'html', 'text/html'],
        ['/build/assets/style.css', 'asset', 'application/javascript'],
    ]) {
        assert.equal(
            (
                await forwardRequest(new Request(url + path), env, async () =>
                    content(kind, type),
                )
            ).status,
            502,
        );
    }
});
await test('tenant denials and upstream failures are generic, bodyless for HEAD', async () => {
    for (const method of ['GET', 'HEAD']) {
        const response = await forwardRequest(
            new Request(url + '/', { method }),
            env,
            async () =>
                new Response(
                    method === 'HEAD' ? null : '{"error":"not_found"}',
                    {
                        status: 404,
                        headers: { 'Content-Type': 'application/json' },
                    },
                ),
        );
        assert.equal(response.status, 404);
        assert.equal(
            await response.text(),
            method === 'HEAD' ? '' : '{"error":"not_found"}',
        );
    }
    assert.equal(
        (
            await forwardRequest(new Request(url + '/'), env, async () =>
                content('html', 'text/html', {}, 302),
            )
        ).status,
        502,
    );
    assert.equal(
        (
            await forwardRequest(
                new Request(url + '/', { method: 'POST' }),
                env,
                offline,
            )
        ).status,
        405,
    );
});

await test('customer queries are forwarded as metadata without changing the fixed upstream', async () => {
    const request = new Request(url + '/about?utm_source=shared');
    const response = await forwardRequest(
        request,
        env,
        async (target, options) => {
            assert.equal(target, env.ORIGIN_ENDPOINT);
            assert.equal(
                options.headers.get('X-Churchsite-Original-Url'),
                request.url,
            );
            return content();
        },
    );
    assert.equal(response.status, 200);
});

await test('HTML GET and HEAD preserve all intersecting origin CSP policies', async () => {
    const policy =
        "default-src 'none', frame-src https://calendar.google.com https://www.openstreetmap.org";
    for (const method of ['GET', 'HEAD']) {
        const response = await forwardRequest(
            new Request(url, { method }),
            env,
            async () =>
                content('html', 'text/html', {
                    'Content-Security-Policy': policy,
                }),
        );
        assert.equal(response.status, 200);
        assert.equal(response.headers.get('Content-Security-Policy'), policy);
        assert.equal(
            await response.text(),
            method === 'HEAD' ? '' : 'published body',
        );
    }
    const asset = await forwardRequest(
        new Request(url + '/build/assets/style-abc.css'),
        env,
        async () =>
            content('asset', 'text/css', { 'Content-Security-Policy': policy }),
    );
    assert.equal(asset.headers.get('Content-Security-Policy'), null);
});
