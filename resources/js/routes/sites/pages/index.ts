import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../wayfinder';
import blocks from './blocks';
/**
 * @see \App\Http\Controllers\SitePageController::store
 * @see app/Http/Controllers/SitePageController.php:14
 * @route '/sites/{site}/pages'
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
    url: '/sites/{site}/pages',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SitePageController::store
 * @see app/Http/Controllers/SitePageController.php:14
 * @route '/sites/{site}/pages'
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
 * @see \App\Http\Controllers\SitePageController::store
 * @see app/Http/Controllers/SitePageController.php:14
 * @route '/sites/{site}/pages'
 */
store.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SitePageController::store
 * @see app/Http/Controllers/SitePageController.php:14
 * @route '/sites/{site}/pages'
 */
const storeForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SitePageController::store
 * @see app/Http/Controllers/SitePageController.php:14
 * @route '/sites/{site}/pages'
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
 * @see \App\Http\Controllers\SitePageController::order
 * @see app/Http/Controllers/SitePageController.php:39
 * @route '/sites/{site}/pages/order'
 */
export const order = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: order.url(args, options),
    method: 'patch',
});

order.definition = {
    methods: ['patch'],
    url: '/sites/{site}/pages/order',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SitePageController::order
 * @see app/Http/Controllers/SitePageController.php:39
 * @route '/sites/{site}/pages/order'
 */
order.url = (
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
        order.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SitePageController::order
 * @see app/Http/Controllers/SitePageController.php:39
 * @route '/sites/{site}/pages/order'
 */
order.patch = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: order.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SitePageController::order
 * @see app/Http/Controllers/SitePageController.php:39
 * @route '/sites/{site}/pages/order'
 */
const orderForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: order.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SitePageController::order
 * @see app/Http/Controllers/SitePageController.php:39
 * @route '/sites/{site}/pages/order'
 */
orderForm.patch = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: order.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

order.form = orderForm;

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
export const show = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

show.definition = {
    methods: ['get', 'head'],
    url: '/sites/{site}/pages/{page}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
show.url = (
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
        show.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
show.get = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
show.head = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
const showForm = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
showForm.get = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
showForm.head = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
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

/**
 * @see \App\Http\Controllers\SitePageController::update
 * @see app/Http/Controllers/SitePageController.php:29
 * @route '/sites/{site}/pages/{page}'
 */
export const update = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

update.definition = {
    methods: ['patch'],
    url: '/sites/{site}/pages/{page}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SitePageController::update
 * @see app/Http/Controllers/SitePageController.php:29
 * @route '/sites/{site}/pages/{page}'
 */
update.url = (
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
        update.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SitePageController::update
 * @see app/Http/Controllers/SitePageController.php:29
 * @route '/sites/{site}/pages/{page}'
 */
update.patch = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SitePageController::update
 * @see app/Http/Controllers/SitePageController.php:29
 * @route '/sites/{site}/pages/{page}'
 */
const updateForm = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
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
 * @see \App\Http\Controllers\SitePageController::update
 * @see app/Http/Controllers/SitePageController.php:29
 * @route '/sites/{site}/pages/{page}'
 */
updateForm.patch = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
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
 * @see \App\Http\Controllers\SitePageController::destroy
 * @see app/Http/Controllers/SitePageController.php:57
 * @route '/sites/{site}/pages/{page}'
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
    url: '/sites/{site}/pages/{page}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\SitePageController::destroy
 * @see app/Http/Controllers/SitePageController.php:57
 * @route '/sites/{site}/pages/{page}'
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
 * @see \App\Http\Controllers\SitePageController::destroy
 * @see app/Http/Controllers/SitePageController.php:57
 * @route '/sites/{site}/pages/{page}'
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
 * @see \App\Http\Controllers\SitePageController::destroy
 * @see app/Http/Controllers/SitePageController.php:57
 * @route '/sites/{site}/pages/{page}'
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
 * @see \App\Http\Controllers\SitePageController::destroy
 * @see app/Http/Controllers/SitePageController.php:57
 * @route '/sites/{site}/pages/{page}'
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

const pages = {
    store: Object.assign(store, store),
    order: Object.assign(order, order),
    show: Object.assign(show, show),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    blocks: Object.assign(blocks, blocks),
};

export default pages;
