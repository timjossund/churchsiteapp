import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { openStreetMapLinks } from '../resources/js/lib/open-street-map.ts';

const fixtures = JSON.parse(
    readFileSync(
        new URL('./Fixtures/open-street-map.json', import.meta.url),
        'utf8',
    ),
);

for (const [name, [fixture]] of Object.entries(fixtures)) {
    await test(name, () => {
        assert.deepEqual(openStreetMapLinks(fixture.source), fixture.expected);
    });
}
