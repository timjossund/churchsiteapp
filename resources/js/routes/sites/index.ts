import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
import billing from './billing'
import published from './published'
import domain from './domain'
import pages from './pages'
import blocks from './blocks'
import logo from './logo'
import socialImage from './social-image'
import media from './media'
/**
* @see \App\Http\Controllers\SiteController::store
* @see app/Http/Controllers/SiteController.php:46
* @route '/sites'
*/
export const store = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/sites',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteController::store
* @see app/Http/Controllers/SiteController.php:46
* @route '/sites'
*/
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::store
* @see app/Http/Controllers/SiteController.php:46
* @route '/sites'
*/
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteController::store
* @see app/Http/Controllers/SiteController.php:46
* @route '/sites'
*/
const storeForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteController::store
* @see app/Http/Controllers/SiteController.php:46
* @route '/sites'
*/
storeForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
})

store.form = storeForm

/**
* @see \App\Http\Controllers\SiteController::goLive
* @see app/Http/Controllers/SiteController.php:156
* @route '/sites/{site}/go-live'
*/
export const goLive = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: goLive.url(args, options),
    method: 'get',
})

goLive.definition = {
    methods: ["get","head"],
    url: '/sites/{site}/go-live',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SiteController::goLive
* @see app/Http/Controllers/SiteController.php:156
* @route '/sites/{site}/go-live'
*/
goLive.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return goLive.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::goLive
* @see app/Http/Controllers/SiteController.php:156
* @route '/sites/{site}/go-live'
*/
goLive.get = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: goLive.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SiteController::goLive
* @see app/Http/Controllers/SiteController.php:156
* @route '/sites/{site}/go-live'
*/
goLive.head = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: goLive.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SiteController::goLive
* @see app/Http/Controllers/SiteController.php:156
* @route '/sites/{site}/go-live'
*/
const goLiveForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: goLive.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SiteController::goLive
* @see app/Http/Controllers/SiteController.php:156
* @route '/sites/{site}/go-live'
*/
goLiveForm.get = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: goLive.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SiteController::goLive
* @see app/Http/Controllers/SiteController.php:156
* @route '/sites/{site}/go-live'
*/
goLiveForm.head = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: goLive.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

goLive.form = goLiveForm

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:53
* @route '/sites/{site}'
*/
export const show = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/sites/{site}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:53
* @route '/sites/{site}'
*/
show.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:53
* @route '/sites/{site}'
*/
show.get = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:53
* @route '/sites/{site}'
*/
show.head = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:53
* @route '/sites/{site}'
*/
const showForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:53
* @route '/sites/{site}'
*/
showForm.get = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\SiteController::show
* @see app/Http/Controllers/SiteController.php:53
* @route '/sites/{site}'
*/
showForm.head = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

show.form = showForm

/**
* @see \App\Http\Controllers\SiteController::destroy
* @see app/Http/Controllers/SiteController.php:37
* @route '/sites/{site}'
*/
export const destroy = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/sites/{site}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\SiteController::destroy
* @see app/Http/Controllers/SiteController.php:37
* @route '/sites/{site}'
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
* @see \App\Http\Controllers\SiteController::destroy
* @see app/Http/Controllers/SiteController.php:37
* @route '/sites/{site}'
*/
destroy.delete = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

/**
* @see \App\Http\Controllers\SiteController::destroy
* @see app/Http/Controllers/SiteController.php:37
* @route '/sites/{site}'
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
* @see \App\Http\Controllers\SiteController::destroy
* @see app/Http/Controllers/SiteController.php:37
* @route '/sites/{site}'
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

/**
* @see \App\Http\Controllers\SiteController::update
* @see app/Http/Controllers/SiteController.php:167
* @route '/sites/{site}'
*/
export const update = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/sites/{site}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\SiteController::update
* @see app/Http/Controllers/SiteController.php:167
* @route '/sites/{site}'
*/
update.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return update.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteController::update
* @see app/Http/Controllers/SiteController.php:167
* @route '/sites/{site}'
*/
update.patch = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\SiteController::update
* @see app/Http/Controllers/SiteController.php:167
* @route '/sites/{site}'
*/
const updateForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteController::update
* @see app/Http/Controllers/SiteController.php:167
* @route '/sites/{site}'
*/
updateForm.patch = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\SitePublishingController::publish
* @see app/Http/Controllers/SitePublishingController.php:13
* @route '/sites/{site}/publish'
*/
export const publish = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

publish.definition = {
    methods: ["post"],
    url: '/sites/{site}/publish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SitePublishingController::publish
* @see app/Http/Controllers/SitePublishingController.php:13
* @route '/sites/{site}/publish'
*/
publish.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return publish.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SitePublishingController::publish
* @see app/Http/Controllers/SitePublishingController.php:13
* @route '/sites/{site}/publish'
*/
publish.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SitePublishingController::publish
* @see app/Http/Controllers/SitePublishingController.php:13
* @route '/sites/{site}/publish'
*/
const publishForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: publish.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SitePublishingController::publish
* @see app/Http/Controllers/SitePublishingController.php:13
* @route '/sites/{site}/publish'
*/
publishForm.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: publish.url(args, options),
    method: 'post',
})

publish.form = publishForm

const sites = {
    billing: Object.assign(billing, billing),
    published: Object.assign(published, published),
    domain: Object.assign(domain, domain),
    store: Object.assign(store, store),
    goLive: Object.assign(goLive, goLive),
    show: Object.assign(show, show),
    destroy: Object.assign(destroy, destroy),
    update: Object.assign(update, update),
    pages: Object.assign(pages, pages),
    publish: Object.assign(publish, publish),
    blocks: Object.assign(blocks, blocks),
    logo: Object.assign(logo, logo),
    socialImage: Object.assign(socialImage, socialImage),
    media: Object.assign(media, media),
}

export default sites