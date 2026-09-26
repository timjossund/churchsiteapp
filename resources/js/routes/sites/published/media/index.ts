import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
export const show = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

show.definition = {
    methods: ['get', 'head'],
    url: '/s/{slug}/media/{mediaAsset}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
show.url = (
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
        show.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace('{mediaAsset}', parsedArgs.mediaAsset.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
show.get = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
show.head = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
const showForm = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
showForm.get = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\PublishedSiteController::show
 * @see app/Http/Controllers/PublishedSiteController.php:109
 * @route '/s/{slug}/media/{mediaAsset}'
 */
showForm.head = (
    args:
        | { slug: string | number; mediaAsset: string | number }
        | [slug: string | number, mediaAsset: string | number],
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

const media = {
    show: Object.assign(show, show),
};

export default media;
