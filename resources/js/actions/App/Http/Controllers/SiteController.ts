import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\SiteController::index
 * @see app/Http/Controllers/SiteController.php:20
 * @route '/dashboard'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

index.definition = {
    methods: ['get', 'head'],
    url: '/dashboard',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\SiteController::index
 * @see app/Http/Controllers/SiteController.php:20
 * @route '/dashboard'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\SiteController::index
 * @see app/Http/Controllers/SiteController.php:20
 * @route '/dashboard'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::index
 * @see app/Http/Controllers/SiteController.php:20
 * @route '/dashboard'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\SiteController::index
 * @see app/Http/Controllers/SiteController.php:20
 * @route '/dashboard'
 */
const indexForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::index
 * @see app/Http/Controllers/SiteController.php:20
 * @route '/dashboard'
 */
indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url(options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::index
 * @see app/Http/Controllers/SiteController.php:20
 * @route '/dashboard'
 */
indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: index.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

index.form = indexForm;

/**
 * @see \App\Http\Controllers\SiteController::store
 * @see app/Http/Controllers/SiteController.php:29
 * @route '/sites'
 */
export const store = (
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

store.definition = {
    methods: ['post'],
    url: '/sites',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteController::store
 * @see app/Http/Controllers/SiteController.php:29
 * @route '/sites'
 */
store.url = (options?: RouteQueryOptions) => {
    return store.definition.url + queryParams(options);
};

/**
 * @see \App\Http\Controllers\SiteController::store
 * @see app/Http/Controllers/SiteController.php:29
 * @route '/sites'
 */
store.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteController::store
 * @see app/Http/Controllers/SiteController.php:29
 * @route '/sites'
 */
const storeForm = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteController::store
 * @see app/Http/Controllers/SiteController.php:29
 * @route '/sites'
 */
storeForm.post = (
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: store.url(options),
    method: 'post',
});

store.form = storeForm;

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}'
 */
const showf191c6db5f5282fd865f0b8900f6468d = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: showf191c6db5f5282fd865f0b8900f6468d.url(args, options),
    method: 'get',
});

showf191c6db5f5282fd865f0b8900f6468d.definition = {
    methods: ['get', 'head'],
    url: '/sites/{site}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}'
 */
showf191c6db5f5282fd865f0b8900f6468d.url = (
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
        showf191c6db5f5282fd865f0b8900f6468d.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}'
 */
showf191c6db5f5282fd865f0b8900f6468d.get = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: showf191c6db5f5282fd865f0b8900f6468d.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}'
 */
showf191c6db5f5282fd865f0b8900f6468d.head = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: showf191c6db5f5282fd865f0b8900f6468d.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}'
 */
const showf191c6db5f5282fd865f0b8900f6468dForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: showf191c6db5f5282fd865f0b8900f6468d.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}'
 */
showf191c6db5f5282fd865f0b8900f6468dForm.get = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: showf191c6db5f5282fd865f0b8900f6468d.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}'
 */
showf191c6db5f5282fd865f0b8900f6468dForm.head = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: showf191c6db5f5282fd865f0b8900f6468d.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

showf191c6db5f5282fd865f0b8900f6468d.form =
    showf191c6db5f5282fd865f0b8900f6468dForm;
/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
const show7b87ae99c433cd798e25390270c00445 = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show7b87ae99c433cd798e25390270c00445.url(args, options),
    method: 'get',
});

show7b87ae99c433cd798e25390270c00445.definition = {
    methods: ['get', 'head'],
    url: '/sites/{site}/pages/{page}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
show7b87ae99c433cd798e25390270c00445.url = (
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
        show7b87ae99c433cd798e25390270c00445.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{page}', parsedArgs.page.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
show7b87ae99c433cd798e25390270c00445.get = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show7b87ae99c433cd798e25390270c00445.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
show7b87ae99c433cd798e25390270c00445.head = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: show7b87ae99c433cd798e25390270c00445.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
const show7b87ae99c433cd798e25390270c00445Form = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show7b87ae99c433cd798e25390270c00445.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
show7b87ae99c433cd798e25390270c00445Form.get = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show7b87ae99c433cd798e25390270c00445.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteController::show
 * @see app/Http/Controllers/SiteController.php:36
 * @route '/sites/{site}/pages/{page}'
 */
show7b87ae99c433cd798e25390270c00445Form.head = (
    args:
        | { site: string | number; page: string | number }
        | [site: string | number, page: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show7b87ae99c433cd798e25390270c00445.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

show7b87ae99c433cd798e25390270c00445.form =
    show7b87ae99c433cd798e25390270c00445Form;

/**
 * Multiple routes resolve to \App\Http\Controllers\SiteController::show, so this export is a
 * dictionary keyed by URI rather than a callable. Call a specific route with `show['<uri>'](...)`,
 * or import the route by name from your generated `routes/` directory.
 */
export const show = {
    '/sites/{site}': showf191c6db5f5282fd865f0b8900f6468d,
    '/sites/{site}/pages/{page}': show7b87ae99c433cd798e25390270c00445,
};

/**
 * @see \App\Http\Controllers\SiteController::update
 * @see app/Http/Controllers/SiteController.php:106
 * @route '/sites/{site}'
 */
export const update = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

update.definition = {
    methods: ['patch'],
    url: '/sites/{site}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteController::update
 * @see app/Http/Controllers/SiteController.php:106
 * @route '/sites/{site}'
 */
update.url = (
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
        update.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteController::update
 * @see app/Http/Controllers/SiteController.php:106
 * @route '/sites/{site}'
 */
update.patch = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteController::update
 * @see app/Http/Controllers/SiteController.php:106
 * @route '/sites/{site}'
 */
const updateForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteController::update
 * @see app/Http/Controllers/SiteController.php:106
 * @route '/sites/{site}'
 */
updateForm.patch = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: update.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

update.form = updateForm;

const SiteController = { index, store, show, update };

export default SiteController;
