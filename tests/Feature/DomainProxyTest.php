<?php

use App\Http\Controllers\DomainProxyController;
use Illuminate\Http\Request;

beforeEach(function () {
    config([
        'app.url' => 'https://churchsite.app',
        'domain-proxy.enabled' => true,
        'domain-proxy.secret' => str_repeat('a', 64),
        'domain-proxy.proof_host' => 'test.timjossund.com',
    ]);
    $this->withHeaders([
        'Authorization' => 'Bearer '.str_repeat('a', 64),
        'X-Churchsite-Proxy-Version' => '1',
        'X-Churchsite-Original-Url' => 'https://test.timjossund.com/up',
    ]);
});

test('authenticated proof is stateless and distinct from platform health', function () {
    $this->withCookie('laravel_session', 'untrusted')->get('https://churchsite.app/_domain/request')
        ->assertOk()->assertExactJson(['status' => 'ok', 'transport' => 'worker', 'hostname' => 'test.timjossund.com'])
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeaderMissing('Set-Cookie');
    expect($this->app['request']->hasSession())->toBeFalse();
    $this->getJson('https://churchsite.app/up')->assertOk()->assertExactJson(['status' => 'up']);
});

test('ingress fails closed for missing or invalid configuration', function (string $key, mixed $value) {
    config([$key => $value]);
    $this->get('https://churchsite.app/_domain/request')->assertNotFound()
        ->assertExactJson(['error' => 'not_found'])->assertHeaderMissing('Set-Cookie');
})->with([
    ['domain-proxy.enabled', false], ['domain-proxy.secret', null], ['domain-proxy.secret', 'short'],
    ['domain-proxy.secret', str_repeat('A', 64)], ['domain-proxy.proof_host', null],
    ['domain-proxy.proof_host', 'churchsite.app'], ['domain-proxy.proof_host', '127.0.0.1'],
]);

test('ingress rejects missing wrong and ambiguous credentials', function (?string $authorization) {
    $this->withHeader('Authorization', $authorization ?? '')->get('https://churchsite.app/_domain/request')
        ->assertForbidden()->assertExactJson(['error' => 'forbidden'])->assertHeaderMissing('Set-Cookie');
})->with([null, 'Bearer wrong', 'bearer '.str_repeat('a', 64), 'Bearer '.str_repeat('a', 64).', Bearer wrong']);

test('ingress rejects invalid original urls', function (string $url) {
    $this->withHeader('X-Churchsite-Original-Url', $url)->get('https://churchsite.app/_domain/request')
        ->assertBadRequest()->assertExactJson(['error' => 'invalid_request']);
})->with([
    'http://test.timjossund.com/up', 'https://user@test.timjossund.com/up',
    'https://test.timjossund.com:444/up', 'https://test.timjossund.com/up#fragment',
    'https://test.timjossund.com/up#', 'https://churchsite.app/up', 'https://127.0.0.1/up',
    'https://[::1]/up', 'https://TEST.timjossund.com/up', 'https://test.timjossund.com./up',
    'https://test.timjossund.com\\@evil.com/up', "https://test.timjossund.com/\nup",
    'https://test.timjossund.com/up%ZZ', '//test.timjossund.com/up', '',
]);

test('ingress rejects unknown versions', function () {
    $this->withHeader('X-Churchsite-Proxy-Version', '2')->get('https://churchsite.app/_domain/request')
        ->assertBadRequest()->assertExactJson(['error' => 'invalid_request']);
});

test('ingress cannot dispatch to platform paths or unapproved hosts', function (string $url) {
    $this->withHeader('X-Churchsite-Original-Url', $url)->get('https://churchsite.app/_domain/request')
        ->assertNotFound()->assertExactJson(['error' => 'not_found'])->assertHeaderMissing('Set-Cookie');
})->with([
    'https://other.example.com/up', 'https://test.timjossund.com/login',
    'https://test.timjossund.com/dashboard', 'https://test.timjossund.com/stripe/webhook',
    'https://test.timjossund.com/s/church', 'https://test.timjossund.com/%75p',
    'https://test.timjossund.com/x/../up', 'https://test.timjossund.com/up?',
    'https://test.timjossund.com/up?a=1', 'https://test.timjossund.com/up/',
]);

test('unsupported methods never enter stateful routes', function (string $method) {
    $this->{strtolower($method)}('https://churchsite.app/_domain/request')->assertStatus(405)
        ->assertExactJson(['error' => 'method_not_allowed'])->assertHeader('Allow', 'GET, HEAD')
        ->assertHeaderMissing('Set-Cookie');
})->with(['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']);

test('head responses have no body on success and failure', function (bool $valid) {
    if (! $valid) {
        $this->withHeader('Authorization', 'wrong');
    }
    $response = $this->head('https://churchsite.app/_domain/request')
        ->assertStatus($valid ? 200 : 403)->assertContent('')->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
})->with([true, false]);

test('direct customer host cannot reach any platform route even with forged proxy headers', function (string $method, string $path) {
    $this->withHeaders(['X-Forwarded-Host' => 'churchsite.app', 'Forwarded' => 'host=churchsite.app'])
        ->{strtolower($method)}('https://test.timjossund.com'.$path)->assertNotFound()
        ->assertExactJson(['error' => 'not_found'])->assertHeaderMissing('Set-Cookie');
})->with([
    ['GET', '/login'], ['GET', '/dashboard'], ['POST', '/stripe/webhook'], ['GET', '/s/church'],
    ['GET', '/up'], ['GET', '/_domain/request'], ['POST', '/login'], ['GET', '/'],
]);

test('unexpected ingress exceptions stay generic even with debug enabled', function () {
    config(['app.debug' => true]);
    $this->mock(DomainProxyController::class)->shouldReceive('__invoke')->once()
        ->withArgs(fn (Request $request): bool => ! $request->hasSession())
        ->andThrow(new RuntimeException('private upstream diagnostic'));
    $response = $this->get('https://churchsite.app/_domain/request')->assertStatus(500)
        ->assertExactJson(['error' => 'unavailable'])->assertHeaderMissing('Set-Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

test('host boundary ignores forwarded authority even when the connection is trusted', function () {
    Request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_HOST);
    try {
        $this->withHeader('X-Forwarded-Host', 'churchsite.app')->get('https://test.timjossund.com/login')
            ->assertNotFound()->assertHeaderMissing('Set-Cookie');
    } finally {
        Request::setTrustedProxies([], 0);
    }
});

test('Laravel implements the shared version one protocol fixtures', function () {
    $fixtures = json_decode(file_get_contents(base_path('tests/Fixtures/domain-proxy-v1.json')), true, 512, JSON_THROW_ON_ERROR);
    config(['domain-proxy.secret' => $fixtures['secret'], 'domain-proxy.proof_host' => $fixtures['proof_host']]);
    foreach ($fixtures['cases'] as $case) {
        $this->withHeaders([
            'Authorization' => 'Bearer '.$fixtures['secret'],
            'X-Churchsite-Proxy-Version' => $fixtures['version'],
            'X-Churchsite-Original-Url' => $case['url'],
        ]);
        $response = $this->{strtolower($case['method'])}($fixtures['origin_endpoint'])
            ->assertStatus($case['status'])->assertHeaderMissing('Set-Cookie')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        if ($case['method'] === 'HEAD') {
            $response->assertContent('');
        } else {
            $response->assertExactJson($case['body']);
        }
        expect($response->headers->get('Cache-Control'))->toContain('no-store');
    }
});
