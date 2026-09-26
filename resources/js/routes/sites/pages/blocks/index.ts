import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
import image from './image';
/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
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
    url: '/sites/{site}/pages/{page}/blocks',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
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
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
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
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
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
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
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
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/pages/{page}/blocks/order'
 */
export const order = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: order.url(args, options),
    method: 'patch',
});

order.definition = {
    methods: ['patch'],
    url: '/sites/{site}/pages/{page}/blocks/order',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/pages/{page}/blocks/order'
 */
order.url = (
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
        order.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/pages/{page}/blocks/order'
 */
order.patch = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: order.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/pages/{page}/blocks/order'
 */
const orderForm = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
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
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/pages/{page}/blocks/order'
 */
orderForm.patch = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
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
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
export const update = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

update.definition = {
    methods: ['patch'],
    url: '/sites/{site}/pages/{page}/blocks/{block}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
update.url = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            page: args[1],
            block: args[2],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        page: args.page,
        block: args.block,
    };

    return (
        update.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace('{block}', parsedArgs.block.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
update.patch = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
const updateForm = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
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
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
updateForm.patch = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
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
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
export const destroy = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

destroy.definition = {
    methods: ['delete'],
    url: '/sites/{site}/pages/{page}/blocks/{block}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
destroy.url = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            page: args[1],
            block: args[2],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        page: args.page,
        block: args.block,
    };

    return (
        destroy.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace('{block}', parsedArgs.block.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
destroy.delete = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
const destroyForm = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
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
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
destroyForm.delete = (
    args:
        | {
              site: string | number;
              page: string | number;
              block: string | number;
          }
        | [
              site: string | number,
              page: string | number,
              block: string | number,
          ],
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

const blocks = {
    store: Object.assign(store, store),
    order: Object.assign(order, order),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
    image: Object.assign(image, image),
};

export default blocks;
