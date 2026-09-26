import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
 */
const store0a66634ed0ee6d86ea3aebd8d4b6c89e = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store0a66634ed0ee6d86ea3aebd8d4b6c89e.url(args, options),
    method: 'post',
});

store0a66634ed0ee6d86ea3aebd8d4b6c89e.definition = {
    methods: ['post'],
    url: '/sites/{site}/pages/{page}/blocks',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
 */
store0a66634ed0ee6d86ea3aebd8d4b6c89e.url = (
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
        store0a66634ed0ee6d86ea3aebd8d4b6c89e.definition.url
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
store0a66634ed0ee6d86ea3aebd8d4b6c89e.post = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store0a66634ed0ee6d86ea3aebd8d4b6c89e.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
 */
const store0a66634ed0ee6d86ea3aebd8d4b6c89eForm = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store0a66634ed0ee6d86ea3aebd8d4b6c89e.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/pages/{page}/blocks'
 */
store0a66634ed0ee6d86ea3aebd8d4b6c89eForm.post = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store0a66634ed0ee6d86ea3aebd8d4b6c89e.url(args, options),
    method: 'post',
});

store0a66634ed0ee6d86ea3aebd8d4b6c89e.form =
    store0a66634ed0ee6d86ea3aebd8d4b6c89eForm;
/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/blocks'
 */
const store5c7937d19865d6cc910a3a4742eddf24 = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store5c7937d19865d6cc910a3a4742eddf24.url(args, options),
    method: 'post',
});

store5c7937d19865d6cc910a3a4742eddf24.definition = {
    methods: ['post'],
    url: '/sites/{site}/blocks',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/blocks'
 */
store5c7937d19865d6cc910a3a4742eddf24.url = (
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
        store5c7937d19865d6cc910a3a4742eddf24.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/blocks'
 */
store5c7937d19865d6cc910a3a4742eddf24.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store5c7937d19865d6cc910a3a4742eddf24.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/blocks'
 */
const store5c7937d19865d6cc910a3a4742eddf24Form = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store5c7937d19865d6cc910a3a4742eddf24.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::store
 * @see app/Http/Controllers/SiteBlockController.php:16
 * @route '/sites/{site}/blocks'
 */
store5c7937d19865d6cc910a3a4742eddf24Form.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store5c7937d19865d6cc910a3a4742eddf24.url(args, options),
    method: 'post',
});

store5c7937d19865d6cc910a3a4742eddf24.form =
    store5c7937d19865d6cc910a3a4742eddf24Form;

/**
 * Multiple routes resolve to \App\Http\Controllers\SiteBlockController::store, so this export is a
 * dictionary keyed by URI rather than a callable. Call a specific route with `store['<uri>'](...)`,
 * or import the route by name from your generated `routes/` directory.
 */
export const store = {
    '/sites/{site}/pages/{page}/blocks': store0a66634ed0ee6d86ea3aebd8d4b6c89e,
    '/sites/{site}/blocks': store5c7937d19865d6cc910a3a4742eddf24,
};

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/pages/{page}/blocks/order'
 */
const orderae19dd920148d682ed68706986365f83 = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: orderae19dd920148d682ed68706986365f83.url(args, options),
    method: 'patch',
});

orderae19dd920148d682ed68706986365f83.definition = {
    methods: ['patch'],
    url: '/sites/{site}/pages/{page}/blocks/order',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/pages/{page}/blocks/order'
 */
orderae19dd920148d682ed68706986365f83.url = (
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
        orderae19dd920148d682ed68706986365f83.definition.url
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
orderae19dd920148d682ed68706986365f83.patch = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: orderae19dd920148d682ed68706986365f83.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/pages/{page}/blocks/order'
 */
const orderae19dd920148d682ed68706986365f83Form = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: orderae19dd920148d682ed68706986365f83.url(args, {
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
orderae19dd920148d682ed68706986365f83Form.patch = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: orderae19dd920148d682ed68706986365f83.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

orderae19dd920148d682ed68706986365f83.form =
    orderae19dd920148d682ed68706986365f83Form;
/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/blocks/order'
 */
const ordere3aeea529e8e10deb029ad61370673ad = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: ordere3aeea529e8e10deb029ad61370673ad.url(args, options),
    method: 'patch',
});

ordere3aeea529e8e10deb029ad61370673ad.definition = {
    methods: ['patch'],
    url: '/sites/{site}/blocks/order',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/blocks/order'
 */
ordere3aeea529e8e10deb029ad61370673ad.url = (
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
        ordere3aeea529e8e10deb029ad61370673ad.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/blocks/order'
 */
ordere3aeea529e8e10deb029ad61370673ad.patch = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: ordere3aeea529e8e10deb029ad61370673ad.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::order
 * @see app/Http/Controllers/SiteBlockController.php:109
 * @route '/sites/{site}/blocks/order'
 */
const ordere3aeea529e8e10deb029ad61370673adForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: ordere3aeea529e8e10deb029ad61370673ad.url(args, {
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
 * @route '/sites/{site}/blocks/order'
 */
ordere3aeea529e8e10deb029ad61370673adForm.patch = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: ordere3aeea529e8e10deb029ad61370673ad.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

ordere3aeea529e8e10deb029ad61370673ad.form =
    ordere3aeea529e8e10deb029ad61370673adForm;

/**
 * Multiple routes resolve to \App\Http\Controllers\SiteBlockController::order, so this export is a
 * dictionary keyed by URI rather than a callable. Call a specific route with `order['<uri>'](...)`,
 * or import the route by name from your generated `routes/` directory.
 */
export const order = {
    '/sites/{site}/pages/{page}/blocks/order':
        orderae19dd920148d682ed68706986365f83,
    '/sites/{site}/blocks/order': ordere3aeea529e8e10deb029ad61370673ad,
};

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
const updatea954e5f2875e8b8aa9f18f25506db8a1 = (
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
    url: updatea954e5f2875e8b8aa9f18f25506db8a1.url(args, options),
    method: 'patch',
});

updatea954e5f2875e8b8aa9f18f25506db8a1.definition = {
    methods: ['patch'],
    url: '/sites/{site}/pages/{page}/blocks/{block}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
updatea954e5f2875e8b8aa9f18f25506db8a1.url = (
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
        updatea954e5f2875e8b8aa9f18f25506db8a1.definition.url
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
updatea954e5f2875e8b8aa9f18f25506db8a1.patch = (
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
    url: updatea954e5f2875e8b8aa9f18f25506db8a1.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
const updatea954e5f2875e8b8aa9f18f25506db8a1Form = (
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
    action: updatea954e5f2875e8b8aa9f18f25506db8a1.url(args, {
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
updatea954e5f2875e8b8aa9f18f25506db8a1Form.patch = (
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
    action: updatea954e5f2875e8b8aa9f18f25506db8a1.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

updatea954e5f2875e8b8aa9f18f25506db8a1.form =
    updatea954e5f2875e8b8aa9f18f25506db8a1Form;
/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/blocks/{block}'
 */
const update384a1139c967221040dcf01c4506ebd9 = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update384a1139c967221040dcf01c4506ebd9.url(args, options),
    method: 'patch',
});

update384a1139c967221040dcf01c4506ebd9.definition = {
    methods: ['patch'],
    url: '/sites/{site}/blocks/{block}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/blocks/{block}'
 */
update384a1139c967221040dcf01c4506ebd9.url = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            block: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        block: args.block,
    };

    return (
        update384a1139c967221040dcf01c4506ebd9.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{block}', parsedArgs.block.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/blocks/{block}'
 */
update384a1139c967221040dcf01c4506ebd9.patch = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update384a1139c967221040dcf01c4506ebd9.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::update
 * @see app/Http/Controllers/SiteBlockController.php:48
 * @route '/sites/{site}/blocks/{block}'
 */
const update384a1139c967221040dcf01c4506ebd9Form = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update384a1139c967221040dcf01c4506ebd9.url(args, {
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
 * @route '/sites/{site}/blocks/{block}'
 */
update384a1139c967221040dcf01c4506ebd9Form.patch = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update384a1139c967221040dcf01c4506ebd9.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

update384a1139c967221040dcf01c4506ebd9.form =
    update384a1139c967221040dcf01c4506ebd9Form;

/**
 * Multiple routes resolve to \App\Http\Controllers\SiteBlockController::update, so this export is a
 * dictionary keyed by URI rather than a callable. Call a specific route with `update['<uri>'](...)`,
 * or import the route by name from your generated `routes/` directory.
 */
export const update = {
    '/sites/{site}/pages/{page}/blocks/{block}':
        updatea954e5f2875e8b8aa9f18f25506db8a1,
    '/sites/{site}/blocks/{block}': update384a1139c967221040dcf01c4506ebd9,
};

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
const destroya954e5f2875e8b8aa9f18f25506db8a1 = (
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
    url: destroya954e5f2875e8b8aa9f18f25506db8a1.url(args, options),
    method: 'delete',
});

destroya954e5f2875e8b8aa9f18f25506db8a1.definition = {
    methods: ['delete'],
    url: '/sites/{site}/pages/{page}/blocks/{block}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
destroya954e5f2875e8b8aa9f18f25506db8a1.url = (
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
        destroya954e5f2875e8b8aa9f18f25506db8a1.definition.url
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
destroya954e5f2875e8b8aa9f18f25506db8a1.delete = (
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
    url: destroya954e5f2875e8b8aa9f18f25506db8a1.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/pages/{page}/blocks/{block}'
 */
const destroya954e5f2875e8b8aa9f18f25506db8a1Form = (
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
    action: destroya954e5f2875e8b8aa9f18f25506db8a1.url(args, {
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
destroya954e5f2875e8b8aa9f18f25506db8a1Form.delete = (
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
    action: destroya954e5f2875e8b8aa9f18f25506db8a1.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

destroya954e5f2875e8b8aa9f18f25506db8a1.form =
    destroya954e5f2875e8b8aa9f18f25506db8a1Form;
/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/blocks/{block}'
 */
const destroy384a1139c967221040dcf01c4506ebd9 = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy384a1139c967221040dcf01c4506ebd9.url(args, options),
    method: 'delete',
});

destroy384a1139c967221040dcf01c4506ebd9.definition = {
    methods: ['delete'],
    url: '/sites/{site}/blocks/{block}',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/blocks/{block}'
 */
destroy384a1139c967221040dcf01c4506ebd9.url = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            block: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        block: args.block,
    };

    return (
        destroy384a1139c967221040dcf01c4506ebd9.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{block}', parsedArgs.block.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/blocks/{block}'
 */
destroy384a1139c967221040dcf01c4506ebd9.delete = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: destroy384a1139c967221040dcf01c4506ebd9.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\SiteBlockController::destroy
 * @see app/Http/Controllers/SiteBlockController.php:77
 * @route '/sites/{site}/blocks/{block}'
 */
const destroy384a1139c967221040dcf01c4506ebd9Form = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: destroy384a1139c967221040dcf01c4506ebd9.url(args, {
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
 * @route '/sites/{site}/blocks/{block}'
 */
destroy384a1139c967221040dcf01c4506ebd9Form.delete = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: destroy384a1139c967221040dcf01c4506ebd9.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

destroy384a1139c967221040dcf01c4506ebd9.form =
    destroy384a1139c967221040dcf01c4506ebd9Form;

/**
 * Multiple routes resolve to \App\Http\Controllers\SiteBlockController::destroy, so this export is a
 * dictionary keyed by URI rather than a callable. Call a specific route with `destroy['<uri>'](...)`,
 * or import the route by name from your generated `routes/` directory.
 */
export const destroy = {
    '/sites/{site}/pages/{page}/blocks/{block}':
        destroya954e5f2875e8b8aa9f18f25506db8a1,
    '/sites/{site}/blocks/{block}': destroy384a1139c967221040dcf01c4506ebd9,
};

const SiteBlockController = { store, order, update, destroy };

export default SiteBlockController;
