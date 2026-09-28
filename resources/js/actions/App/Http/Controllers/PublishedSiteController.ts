import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\PublishedSiteController::media
* @see app/Http/Controllers/PublishedSiteController.php:141
* @route '/s/{slug}/media/{mediaAsset}'
*/
export const media = (args: { slug: string | number, mediaAsset: string | number } | [slug: string | number, mediaAsset: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: media.url(args, options),
    method: 'get',
})

media.definition = {
    methods: ["get","head"],
    url: '/s/{slug}/media/{mediaAsset}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublishedSiteController::media
* @see app/Http/Controllers/PublishedSiteController.php:141
* @route '/s/{slug}/media/{mediaAsset}'
*/
media.url = (args: { slug: string | number, mediaAsset: string | number } | [slug: string | number, mediaAsset: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            slug: args[0],
            mediaAsset: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        slug: args.slug,
        mediaAsset: args.mediaAsset,
    }

    return media.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace('{mediaAsset}', parsedArgs.mediaAsset.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublishedSiteController::media
* @see app/Http/Controllers/PublishedSiteController.php:141
* @route '/s/{slug}/media/{mediaAsset}'
*/
media.get = (args: { slug: string | number, mediaAsset: string | number } | [slug: string | number, mediaAsset: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: media.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::media
* @see app/Http/Controllers/PublishedSiteController.php:141
* @route '/s/{slug}/media/{mediaAsset}'
*/
media.head = (args: { slug: string | number, mediaAsset: string | number } | [slug: string | number, mediaAsset: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: media.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::media
* @see app/Http/Controllers/PublishedSiteController.php:141
* @route '/s/{slug}/media/{mediaAsset}'
*/
const mediaForm = (args: { slug: string | number, mediaAsset: string | number } | [slug: string | number, mediaAsset: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: media.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::media
* @see app/Http/Controllers/PublishedSiteController.php:141
* @route '/s/{slug}/media/{mediaAsset}'
*/
mediaForm.get = (args: { slug: string | number, mediaAsset: string | number } | [slug: string | number, mediaAsset: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: media.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::media
* @see app/Http/Controllers/PublishedSiteController.php:141
* @route '/s/{slug}/media/{mediaAsset}'
*/
mediaForm.head = (args: { slug: string | number, mediaAsset: string | number } | [slug: string | number, mediaAsset: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: media.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

media.form = mediaForm

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}'
*/
const show38159059e6dacee9cbb1d4bfd9ea5ddd = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show38159059e6dacee9cbb1d4bfd9ea5ddd.url(args, options),
    method: 'get',
})

show38159059e6dacee9cbb1d4bfd9ea5ddd.definition = {
    methods: ["get","head"],
    url: '/s/{slug}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}'
*/
show38159059e6dacee9cbb1d4bfd9ea5ddd.url = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { slug: args }
    }

    if (Array.isArray(args)) {
        args = {
            slug: args[0],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        slug: args.slug,
    }

    return show38159059e6dacee9cbb1d4bfd9ea5ddd.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}'
*/
show38159059e6dacee9cbb1d4bfd9ea5ddd.get = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show38159059e6dacee9cbb1d4bfd9ea5ddd.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}'
*/
show38159059e6dacee9cbb1d4bfd9ea5ddd.head = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show38159059e6dacee9cbb1d4bfd9ea5ddd.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}'
*/
const show38159059e6dacee9cbb1d4bfd9ea5dddForm = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show38159059e6dacee9cbb1d4bfd9ea5ddd.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}'
*/
show38159059e6dacee9cbb1d4bfd9ea5dddForm.get = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show38159059e6dacee9cbb1d4bfd9ea5ddd.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}'
*/
show38159059e6dacee9cbb1d4bfd9ea5dddForm.head = (args: { slug: string | number } | [slug: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show38159059e6dacee9cbb1d4bfd9ea5ddd.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show38159059e6dacee9cbb1d4bfd9ea5ddd.form = show38159059e6dacee9cbb1d4bfd9ea5dddForm
/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}/{path}'
*/
const show2e523a8772178ac65aafc0437bb2ae0a = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show2e523a8772178ac65aafc0437bb2ae0a.url(args, options),
    method: 'get',
})

show2e523a8772178ac65aafc0437bb2ae0a.definition = {
    methods: ["get","head"],
    url: '/s/{slug}/{path}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}/{path}'
*/
show2e523a8772178ac65aafc0437bb2ae0a.url = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions) => {
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

    return show2e523a8772178ac65aafc0437bb2ae0a.definition.url
            .replace('{slug}', parsedArgs.slug.toString())
            .replace('{path}', parsedArgs.path.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}/{path}'
*/
show2e523a8772178ac65aafc0437bb2ae0a.get = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show2e523a8772178ac65aafc0437bb2ae0a.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}/{path}'
*/
show2e523a8772178ac65aafc0437bb2ae0a.head = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show2e523a8772178ac65aafc0437bb2ae0a.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}/{path}'
*/
const show2e523a8772178ac65aafc0437bb2ae0aForm = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show2e523a8772178ac65aafc0437bb2ae0a.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}/{path}'
*/
show2e523a8772178ac65aafc0437bb2ae0aForm.get = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show2e523a8772178ac65aafc0437bb2ae0a.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\PublishedSiteController::show
* @see app/Http/Controllers/PublishedSiteController.php:16
* @route '/s/{slug}/{path}'
*/
show2e523a8772178ac65aafc0437bb2ae0aForm.head = (args: { slug: string | number, path: string | number } | [slug: string | number, path: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show2e523a8772178ac65aafc0437bb2ae0a.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show2e523a8772178ac65aafc0437bb2ae0a.form = show2e523a8772178ac65aafc0437bb2ae0aForm

/**
* Multiple routes resolve to \App\Http\Controllers\PublishedSiteController::show, so this export is a
* dictionary keyed by URI rather than a callable. Call a specific route with `show['<uri>'](...)`,
* or import the route by name from your generated `routes/` directory.
*/
export const show = {
    '/s/{slug}': show38159059e6dacee9cbb1d4bfd9ea5ddd,
    '/s/{slug}/{path}': show2e523a8772178ac65aafc0437bb2ae0a,
}

const PublishedSiteController = { media, show }

export default PublishedSiteController