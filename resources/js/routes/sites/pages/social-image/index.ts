import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/pages/{page}/social-image'
 */
export const store = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/sites/{site}/pages/{page}/social-image',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/pages/{page}/social-image'
 */
store.url = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            page: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        page: args.page,
    };

    return (
        store.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/pages/{page}/social-image'
 */
store.post = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/pages/{page}/social-image'
 */
const storeForm = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:54
 * @route '/sites/{site}/pages/{page}/social-image'
 */
storeForm.post = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
});

store.form = storeForm;

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:76
 * @route '/sites/{site}/pages/{page}/social-image'
 */
export const destroy = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

destroy.definition = {
    methods: ['delete'],
    url: '/sites/{site}/pages/{page}/social-image',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:76
 * @route '/sites/{site}/pages/{page}/social-image'
 */
destroy.url = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            page: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        page: args.page,
    };

    return (
        destroy.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:76
 * @route '/sites/{site}/pages/{page}/social-image'
 */
destroy.delete = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::destroy
 * @see app/Http/Controllers/SiteMediaController.php:76
 * @route '/sites/{site}/pages/{page}/social-image'
 */
const destroyForm = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
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
 * @see app/Http/Controllers/SiteMediaController.php:76
 * @route '/sites/{site}/pages/{page}/social-image'
 */
destroyForm.delete = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
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
