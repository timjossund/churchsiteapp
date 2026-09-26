import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\SitePublishingController::publish
 * @see app/Http/Controllers/SitePublishingController.php:13
 * @route '/sites/{site}/publish'
 */
export const publish = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
});

publish.definition = {
    methods: ['post'],
    url: '/sites/{site}/publish',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SitePublishingController::publish
 * @see app/Http/Controllers/SitePublishingController.php:13
 * @route '/sites/{site}/publish'
 */
publish.url = (
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
        publish.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SitePublishingController::publish
 * @see app/Http/Controllers/SitePublishingController.php:13
 * @route '/sites/{site}/publish'
 */
publish.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SitePublishingController::publish
 * @see app/Http/Controllers/SitePublishingController.php:13
 * @route '/sites/{site}/publish'
 */
const publishForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: publish.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SitePublishingController::publish
 * @see app/Http/Controllers/SitePublishingController.php:13
 * @route '/sites/{site}/publish'
 */
publishForm.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: publish.url(args, options),
    method: 'post',
});

publish.form = publishForm;

const SitePublishingController = { publish };

export default SitePublishingController;
