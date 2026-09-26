import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../wayfinder';
import media from './media';
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

const published = {
    media: Object.assign(media, media),
    show: Object.assign(show, show),
};

export default published;
