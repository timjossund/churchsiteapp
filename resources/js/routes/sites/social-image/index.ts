import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../wayfinder';
/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/social-image'
 */
export const store = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/sites/{site}/social-image',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/social-image'
 */
store.url = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args };
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
    };

    return (
        store.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/social-image'
 */
store.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/social-image'
 */
const storeForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/social-image'
 */
storeForm.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
});

store.form = storeForm;

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:74
 * @route '/sites/{site}/social-image'
 */
export const destroy = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

destroy.definition = {
    methods: ['delete'],
    url: '/sites/{site}/social-image',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:74
 * @route '/sites/{site}/social-image'
 */
destroy.url = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args };
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
    };

    return (
        destroy.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:74
 * @route '/sites/{site}/social-image'
 */
destroy.delete = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:74
 * @route '/sites/{site}/social-image'
 */
const destroyForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:74
 * @route '/sites/{site}/social-image'
 */
destroyForm.delete = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

destroy.form = destroyForm;

const socialImage = {
    store: Object.assign(store, store),
    destroy: Object.assign(destroy, destroy),
};

export default socialImage;
