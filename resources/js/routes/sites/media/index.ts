import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\SiteMediaController::update
 * @see app/Http/Controllers/SiteMediaController.php:84
 * @route '/sites/{site}/media/{mediaAsset}'
 */
export const update = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

update.definition = {
    methods: ['patch'],
    url: '/sites/{site}/media/{mediaAsset}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::update
 * @see app/Http/Controllers/SiteMediaController.php:84
 * @route '/sites/{site}/media/{mediaAsset}'
 */
update.url = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            mediaAsset: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        mediaAsset: args.mediaAsset,
    };

    return (
        update.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{mediaAsset}', parsedArgs.mediaAsset.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::update
 * @see app/Http/Controllers/SiteMediaController.php:84
 * @route '/sites/{site}/media/{mediaAsset}'
 */
update.patch = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::update
 * @see app/Http/Controllers/SiteMediaController.php:84
 * @route '/sites/{site}/media/{mediaAsset}'
 */
const updateForm = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::update
 * @see app/Http/Controllers/SiteMediaController.php:84
 * @route '/sites/{site}/media/{mediaAsset}'
 */
updateForm.patch = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

update.form = updateForm;

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:95
 * @route '/sites/{site}/media/{mediaAsset}'
 */
export const show = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

show.definition = {
    methods: ['get', 'head'],
    url: '/sites/{site}/media/{mediaAsset}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:95
 * @route '/sites/{site}/media/{mediaAsset}'
 */
show.url = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            mediaAsset: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        mediaAsset: args.mediaAsset,
    };

    return (
        show.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{mediaAsset}', parsedArgs.mediaAsset.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:95
 * @route '/sites/{site}/media/{mediaAsset}'
 */
show.get = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:95
 * @route '/sites/{site}/media/{mediaAsset}'
 */
show.head = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:95
 * @route '/sites/{site}/media/{mediaAsset}'
 */
const showForm = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:95
 * @route '/sites/{site}/media/{mediaAsset}'
 */
showForm.get = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:95
 * @route '/sites/{site}/media/{mediaAsset}'
 */
showForm.head = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
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
    update: Object.assign(update, update),
    show: Object.assign(show, show),
};

export default media;
