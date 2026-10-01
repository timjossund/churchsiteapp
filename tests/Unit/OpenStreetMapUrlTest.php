<?php

use App\Support\OpenStreetMapUrl;

test('OpenStreetMap sharing links produce only trusted map URLs', function (array $fixture) {
    expect(OpenStreetMapUrl::from($fixture['source']))->toBe($fixture['expected']);
})->with(json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/open-street-map.json'), true, 512, JSON_THROW_ON_ERROR));
