import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { googleCalendarUrl } from '../resources/js/lib/google-calendar.ts';
const fixtures = JSON.parse(
    readFileSync(
        new URL('./Fixtures/google-calendar.json', import.meta.url),
        'utf8',
    ),
);
for (const [name, [fixture]] of Object.entries(fixtures)) {
    await test(name, () =>
        assert.equal(googleCalendarUrl(fixture.source), fixture.expected),
    );
}
