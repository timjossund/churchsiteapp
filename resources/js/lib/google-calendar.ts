import timeZones from './google-calendar-time-zones.json' with { type: 'json' };

const supportedTimeZones = new Set(timeZones);

export function googleCalendarUrl(source: string): string | null {
    source = source.trim();
    if (source.length > 8192) return null;
    for (let index = 0; index < source.length; index++) {
        const code = source.charCodeAt(index);
        if (code <= 32 || code === 127) return null;
    }
    if (
        /[\\<>]/.test(source) ||
        /%(?![\da-f]{2})/i.test(source) ||
        /%(?:0[\da-f]|1[\da-f]|7f)/i.test(source)
    )
        return null;
    // Inspect original spelling: URL normalization must not hide unsafe paths.
    const match =
        /^https:\/\/calendar\.google\.com(?::443)?\/calendar\/embed\?([^#]+)$/i.exec(
            source,
        );
    if (!match || !source.includes('/calendar/embed?')) return null;
    const parameters = new Map<string, string>();
    for (const pair of match[1].split('&')) {
        const entries = [...new URLSearchParams(pair)];
        if (entries.length !== 1) return null;
        const [key, value] = entries[0];
        if (!/^[a-zA-Z0-9_-]+$/.test(key) || parameters.has(key)) return null;
        parameters.set(key, value);
    }
    const id = parameters.get('src') ?? '';
    for (let index = 0; index < id.length; index++) {
        const code = id.charCodeAt(index);
        if (code < 33 || code > 126) return null;
    }
    if (!id || /[<>\\]/.test(id)) return null;
    const mode = parameters.get('mode') ?? 'AGENDA';
    if (!['MONTH', 'WEEK', 'AGENDA'].includes(mode)) return null;
    let query = `src=${encode(id)}`;
    if (parameters.has('ctz')) {
        const timezone = parameters.get('ctz')!;
        if (!supportedTimeZones.has(timezone.toLowerCase())) return null;
        query += `&ctz=${encode(timezone)}`;
    }
    return `https://calendar.google.com/calendar/embed?${query}&mode=${mode}`;
}

function encode(value: string): string {
    return encodeURIComponent(value).replace(
        /[!'()*]/g,
        (character) => `%${character.charCodeAt(0).toString(16).toUpperCase()}`,
    );
}
