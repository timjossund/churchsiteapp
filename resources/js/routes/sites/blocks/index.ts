import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\SiteBlockController::store
* @see app/Http/Controllers/SiteBlockController.php:16
* @route '/sites/{site}/blocks'
*/
export const store = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/sites/{site}/blocks',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteBlockController::store
* @see app/Http/Controllers/SiteBlockController.php:16
* @route '/sites/{site}/blocks'
*/
store.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args }
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: args.site,
    }

    return store.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteBlockController::store
* @see app/Http/Controllers/SiteBlockController.php:16
* @route '/sites/{site}/blocks'
*/
store.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBlockController::store
* @see app/Http/Controllers/SiteBlockController.php:16
* @route '/sites/{site}/blocks'
*/
const storeForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBlockController::store
* @see app/Http/Controllers/SiteBlockController.php:16
* @route '/sites/{site}/blocks'
*/
storeForm.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\SiteBlockController::order
* @see app/Http/Controllers/SiteBlockController.php:94
* @route '/sites/{site}/blocks/order'
*/
export const order = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: order.url(args, options),
    method: 'patch',
})

order.definition = {
    methods: ["patch"],
    url: '/sites/{site}/blocks/order',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\SiteBlockController::order
* @see app/Http/Controllers/SiteBlockController.php:94
* @route '/sites/{site}/blocks/order'
*/
order.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { site: args }
    }

    if (Array.isArray(args)) {
        args = {
            site: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: args.site,
    }

    return order.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteBlockController::order
* @see app/Http/Controllers/SiteBlockController.php:94
* @route '/sites/{site}/blocks/order'
*/
order.patch = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: order.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\SiteBlockController::order
* @see app/Http/Controllers/SiteBlockController.php:94
* @route '/sites/{site}/blocks/order'
*/
const orderForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: order.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBlockController::order
* @see app/Http/Controllers/SiteBlockController.php:94
* @route '/sites/{site}/blocks/order'
*/
orderForm.patch = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: order.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

order.form = orderForm

/**
* @see \App\Http\Controllers\SiteBlockController::update
* @see app/Http/Controllers/SiteBlockController.php:42
* @route '/sites/{site}/blocks/{block}'
*/
export const update = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/sites/{site}/blocks/{block}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\SiteBlockController::update
* @see app/Http/Controllers/SiteBlockController.php:42
* @route '/sites/{site}/blocks/{block}'
*/
update.url = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            block: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: args.site,
        block: args.block,
    }

    return update.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{block}', parsedArgs.block.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteBlockController::update
* @see app/Http/Controllers/SiteBlockController.php:42
* @route '/sites/{site}/blocks/{block}'
*/
update.patch = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\SiteBlockController::update
* @see app/Http/Controllers/SiteBlockController.php:42
* @route '/sites/{site}/blocks/{block}'
*/
const updateForm = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBlockController::update
* @see app/Http/Controllers/SiteBlockController.php:42
* @route '/sites/{site}/blocks/{block}'
*/
updateForm.patch = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

/**
* @see \App\Http\Controllers\SiteBlockController::destroy
* @see app/Http/Controllers/SiteBlockController.php:66
* @route '/sites/{site}/blocks/{block}'
*/
export const destroy = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/sites/{site}/blocks/{block}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\SiteBlockController::destroy
* @see app/Http/Controllers/SiteBlockController.php:66
* @route '/sites/{site}/blocks/{block}'
*/
destroy.url = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            block: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: args.site,
        block: args.block,
    }

    return destroy.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{block}', parsedArgs.block.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteBlockController::destroy
* @see app/Http/Controllers/SiteBlockController.php:66
* @route '/sites/{site}/blocks/{block}'
*/
destroy.delete = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\SiteBlockController::destroy
* @see app/Http/Controllers/SiteBlockController.php:66
* @route '/sites/{site}/blocks/{block}'
*/
const destroyForm = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBlockController::destroy
* @see app/Http/Controllers/SiteBlockController.php:66
* @route '/sites/{site}/blocks/{block}'
*/
destroyForm.delete = (args: { site: string | number, block: string | number } | [site: string | number, block: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const blocks = {
    store: Object.assign(store, store),
    order: Object.assign(order, order),
    update: Object.assign(update, update),
    destroy: Object.assign(destroy, destroy),
}

export default blocks