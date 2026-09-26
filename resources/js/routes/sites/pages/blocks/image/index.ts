import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../../wayfinder';
/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/pages/{page}/blocks/{block}/image'
 */
export const store = (
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
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/sites/{site}/pages/{page}/blocks/{block}/image',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/pages/{page}/blocks/{block}/image'
 */
store.url = (
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
        store.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace('{block}', parsedArgs.block.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/pages/{page}/blocks/{block}/image'
 */
store.post = (
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
): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/pages/{page}/blocks/{block}/image'
 */
const storeForm = (
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
    action: store.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::store
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/pages/{page}/blocks/{block}/image'
 */
storeForm.post = (
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
    action: store.url(args, options),
    method: 'post',
});

store.form = storeForm;

const image = {
    store: Object.assign(store, store),
};

export default image;
