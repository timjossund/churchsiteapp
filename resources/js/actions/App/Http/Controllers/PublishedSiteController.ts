import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\PublishedSiteController::media
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
export const media = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: media.url(args, options),
    method: 'get',
});

media.definition = {
    methods: ['get', 'head'],
    url: '/s/{slug}/media/{mediaAsset}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\PublishedSiteController::media
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
media.url = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            slug: args[0],
            mediaAsset: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        slug: args.slug,
        mediaAsset: args.mediaAsset,
    };

    return (
        media.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace('{mediaAsset}', parsedArgs.mediaAsset.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\PublishedSiteController::media
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
media.get = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: media.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::media
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
media.head = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: media.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::media
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
const mediaForm = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: media.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::media
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
mediaForm.get = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: media.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::media
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
mediaForm.head = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: media.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

media.form = mediaForm;

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:13
 * @route '/s/{slug}'
 */
export const show = (
    args: { slug: string | number } | [slug: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

show.definition = {
    methods: ['get', 'head'],
    url: '/s/{slug}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:13
 * @route '/s/{slug}'
 */
show.url = (
    args: { slug: string | number } | [slug: string | number] | string | number,
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { slug: args };
    }

    if (Array.isArray(args)) {
        args = {
            slug: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        slug: args.slug,
    };

    return (
        show.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:13
 * @route '/s/{slug}'
 */
show.get = (
    args: { slug: string | number } | [slug: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:13
 * @route '/s/{slug}'
 */
show.head = (
    args: { slug: string | number } | [slug: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:13
 * @route '/s/{slug}'
 */
const showForm = (
    args: { slug: string | number } | [slug: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:13
 * @route '/s/{slug}'
 */
showForm.get = (
    args: { slug: string | number } | [slug: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:13
 * @route '/s/{slug}'
 */
showForm.head = (
    args: { slug: string | number } | [slug: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

show.form = showForm;

const PublishedSiteController = { media, show };

export default PublishedSiteController;
