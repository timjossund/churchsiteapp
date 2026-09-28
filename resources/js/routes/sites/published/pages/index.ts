import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:17
* @route '/s/{slug}/{path}'
*/
export const show = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/s/{slug}/{path}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:17
* @route '/s/{slug}/{path}'
*/
show.url = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            slug: args[0],
            path: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        slug: args.slug,
        path: args.path,
    }

    return show.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace('{path}', parsedArgs.path.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:17
* @route '/s/{slug}/{path}'
*/
show.get = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:17
* @route '/s/{slug}/{path}'
*/
show.head = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:17
* @route '/s/{slug}/{path}'
*/
const showForm = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:17
* @route '/s/{slug}/{path}'
*/
showForm.get = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:17
* @route '/s/{slug}/{path}'
*/
showForm.head = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

const pages = {
    show: Object.assign(show, show),
}

export default pages