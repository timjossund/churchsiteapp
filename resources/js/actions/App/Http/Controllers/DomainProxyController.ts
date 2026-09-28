import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
const DomainProxyController = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DomainProxyController.url(options),
    method: 'get',
})

DomainProxyController.definition = {
    methods: ["get","head","post","put","patch","delete","options"],
    url: '/_domain/request',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options"]>

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyController.url = (options?: RouteQueryOptions) => {
    return DomainProxyController.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyController.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: DomainProxyController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyController.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: DomainProxyController.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyController.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: DomainProxyController.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyController.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: DomainProxyController.url(options),
    method: 'put',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyController.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: DomainProxyController.url(options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyController.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: DomainProxyController.url(options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyController.options = (options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: DomainProxyController.url(options),
    method: 'options',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
const DomainProxyControllerForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: DomainProxyController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyControllerForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: DomainProxyController.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyControllerForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: DomainProxyController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyControllerForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: DomainProxyController.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyControllerForm.put = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: DomainProxyController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PUT',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyControllerForm.patch = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: DomainProxyController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyControllerForm.delete = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: DomainProxyController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\DomainProxyController::__invoke
* @see app/Http/Controllers/DomainProxyController.php:16
* @route '/_domain/request'
*/
DomainProxyControllerForm.options = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: DomainProxyController.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'OPTIONS',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

DomainProxyController.form = DomainProxyControllerForm

export default DomainProxyController