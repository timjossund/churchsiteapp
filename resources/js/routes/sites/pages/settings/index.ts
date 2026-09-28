import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\SitePageController::update
* @see app/Http/Controllers/SitePageController.php:43
* @route '/sites/{site}/pages/{page}/settings'
*/
export const update = (args: { site: string | number, page: string | number } | [site: string | number, page: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/sites/{site}/pages/{page}/settings',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\SitePageController::update
* @see app/Http/Controllers/SitePageController.php:43
* @route '/sites/{site}/pages/{page}/settings'
*/
update.url = (args: { site: string | number, page: string | number } | [site: string | number, page: string | number ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            page: args[1],
        }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
        site: args.site,
        page: args.page,
    }

    return update.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SitePageController::update
* @see app/Http/Controllers/SitePageController.php:43
* @route '/sites/{site}/pages/{page}/settings'
*/
update.patch = (args: { site: string | number, page: string | number } | [site: string | number, page: string | number ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

/**
* @see \App\Http\Controllers\SitePageController::update
* @see app/Http/Controllers/SitePageController.php:43
* @route '/sites/{site}/pages/{page}/settings'
*/
const updateForm = (args: { site: string | number, page: string | number } | [site: string | number, page: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SitePageController::update
* @see app/Http/Controllers/SitePageController.php:43
* @route '/sites/{site}/pages/{page}/settings'
*/
updateForm.patch = (args: { site: string | number, page: string | number } | [site: string | number, page: string | number ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'post',
})

update.form = updateForm

const settings = {
    update: Object.assign(update, update),
}

export default settings