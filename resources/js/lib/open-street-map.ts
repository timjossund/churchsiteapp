export type OpenStreetMapLinks = {
    embed_url: string;
    location_url: string;
};

export function openStreetMapLinks(source: string): OpenStreetMapLinks | null {
    source = source.trim();
    for (let index = 0; index < source.length; index++) {
        const code = source.charCodeAt(index);
        if (code <= 32 || code === 127) return null;
    }
    if (
        /[\\<>]/.test(source) ||
        /%(?:0[\da-f]|1[\da-f]|7f)/i.test(source) ||
        /%(?![\da-f]{2})/i.test(source)
    ) {
        return null;
    }
    // Parse the original spelling so URL normalization cannot hide a different path.
    const match = /^https:\/\/([^/?#]+)\/?(?:\?([^#]*))?(?:#.*)?$/i.exec(
        source,
    );
    if (!match || !/^(?:www\.)?openstreetmap\.org(?::443)?$/i.test(match[1])) {
        return null;
    }
    const parameters = new Map<string, string>();
    for (const pair of (match[2] ?? '').split('&')) {
        const entries = [...new URLSearchParams(pair)];
        if (entries.length !== 1) return null;
        const [key, value] = entries[0];
        if (!/^[a-zA-Z0-9_-]+$/.test(key) || parameters.has(key)) return null;
        parameters.set(key, value);
    }
    const latitude = coordinate(parameters.get('mlat') ?? '', 90);
    const longitude = coordinate(parameters.get('mlon') ?? '', 180);
    if (latitude === null || longitude === null) return null;

    const marker = `${formatNumber(latitude)},${formatNumber(longitude)}`;
    const west = Math.max(-180, Math.min(179.98, longitude - 0.01));
    const south = Math.max(-90, Math.min(89.99, latitude - 0.005));
    const bbox = [west, south, west + 0.02, south + 0.01]
        .map(formatNumber)
        .join(',');

    return {
        embed_url: `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bbox)}&layer=mapnik&marker=${encodeURIComponent(marker)}`,
        location_url: `https://www.openstreetmap.org/?mlat=${formatNumber(latitude)}&mlon=${formatNumber(longitude)}#map=16/${formatNumber(latitude)}/${formatNumber(longitude)}`,
    };
}

function coordinate(value: string, limit: number): number | null {
    if (!/^[+-]?(?:[0-9]+(?:\.[0-9]+)?|\.[0-9]+)$/.test(value)) return null;
    const number = Number(value);
    return Number.isFinite(number) && Math.abs(number) <= limit ? number : null;
}

function formatNumber(value: number): string {
    const rounded =
        (Math.sign(value) * Math.round(Math.abs(value) * 1_000_000)) /
        1_000_000;
    const formatted = rounded.toFixed(6).replace(/\.?0+$/, '');
    return formatted === '-0' ? '0' : formatted;
}
