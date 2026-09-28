import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\CustomHostnameController::check
* @see app/Http/Controllers/CustomHostnameController.php:40
* @route '/sites/{site}/domain/check'
*/
export const check = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: check.url(args, options),
    method: 'post',
})

check.definition = {
    methods: ["post"],
    url: '/sites/{site}/domain/check',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CustomHostnameController::check
* @see app/Http/Controllers/CustomHostnameController.php:40
* @route '/sites/{site}/domain/check'
*/
check.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return check.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomHostnameController::check
* @see app/Http/Controllers/CustomHostnameController.php:40
* @route '/sites/{site}/domain/check'
*/
check.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: check.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CustomHostnameController::check
* @see app/Http/Controllers/CustomHostnameController.php:40
* @route '/sites/{site}/domain/check'
*/
const checkForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: check.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CustomHostnameController::check
* @see app/Http/Controllers/CustomHostnameController.php:40
* @route '/sites/{site}/domain/check'
*/
checkForm.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: check.url(args, options),
    method: 'post',
})

check.form = checkForm

/**
* @see \App\Http\Controllers\CustomHostnameController::store
* @see app/Http/Controllers/CustomHostnameController.php:19
* @route '/sites/{site}/domain'
*/
export const store = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/sites/{site}/domain',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\CustomHostnameController::store
* @see app/Http/Controllers/CustomHostnameController.php:19
* @route '/sites/{site}/domain'
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
* @see \App\Http\Controllers\CustomHostnameController::store
* @see app/Http/Controllers/CustomHostnameController.php:19
* @route '/sites/{site}/domain'
*/
store.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CustomHostnameController::store
* @see app/Http/Controllers/CustomHostnameController.php:19
* @route '/sites/{site}/domain'
*/
const storeForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CustomHostnameController::store
* @see app/Http/Controllers/CustomHostnameController.php:19
* @route '/sites/{site}/domain'
*/
storeForm.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(args, options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\CustomHostnameController::destroy
* @see app/Http/Controllers/CustomHostnameController.php:49
* @route '/sites/{site}/domain'
*/
export const destroy = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/sites/{site}/domain',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\CustomHostnameController::destroy
* @see app/Http/Controllers/CustomHostnameController.php:49
* @route '/sites/{site}/domain'
*/
destroy.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return destroy.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\CustomHostnameController::destroy
* @see app/Http/Controllers/CustomHostnameController.php:49
* @route '/sites/{site}/domain'
*/
destroy.delete = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\CustomHostnameController::destroy
* @see app/Http/Controllers/CustomHostnameController.php:49
* @route '/sites/{site}/domain'
*/
const destroyForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\CustomHostnameController::destroy
* @see app/Http/Controllers/CustomHostnameController.php:49
* @route '/sites/{site}/domain'
*/
destroyForm.delete = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: destroy.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

destroy.form = destroyForm

const CustomHostnameController = { check, store, destroy }

export default CustomHostnameController