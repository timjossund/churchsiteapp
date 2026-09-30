<?php

use App\Support\GoogleCalendarUrl;

test('Google Calendar URLs are validated and canonicalized', function (array $fixture) {
    expect(GoogleCalendarUrl::from($fixture['source']))->toBe($fixture['expected']);
})->with(json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/google-calendar.json'), true, 512, JSON_THROW_ON_ERROR));
