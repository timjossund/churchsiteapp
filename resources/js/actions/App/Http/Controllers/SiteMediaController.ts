import {
    queryParams,
    type RouteQueryOptions,
    type RouteDefinition,
    type RouteFormDefinition,
    applyUrlDefaults,
} from './../../../../wayfinder';
/**
 * @see \App\Http\Controllers\SiteMediaController::uploadBlockImage
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/blocks/{block}/image'
 */
export const uploadBlockImage = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: uploadBlockImage.url(args, options),
    method: 'post',
});

uploadBlockImage.definition = {
    methods: ['post'],
    url: '/sites/{site}/blocks/{block}/image',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadBlockImage
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/blocks/{block}/image'
 */
uploadBlockImage.url = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            block: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        block: args.block,
    };

    return (
        uploadBlockImage.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{block}', parsedArgs.block.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadBlockImage
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/blocks/{block}/image'
 */
uploadBlockImage.post = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: uploadBlockImage.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadBlockImage
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/blocks/{block}/image'
 */
const uploadBlockImageForm = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: uploadBlockImage.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadBlockImage
 * @see app/Http/Controllers/SiteMediaController.php:23
 * @route '/sites/{site}/blocks/{block}/image'
 */
uploadBlockImageForm.post = (
    args:
        | { site: string | number; block: string | number }
        | [site: string | number, block: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: uploadBlockImage.url(args, options),
    method: 'post',
});

uploadBlockImage.form = uploadBlockImageForm;

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadLogo
 * @see app/Http/Controllers/SiteMediaController.php:41
 * @route '/sites/{site}/logo'
 */
export const uploadLogo = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: uploadLogo.url(args, options),
    method: 'post',
});

uploadLogo.definition = {
    methods: ['post'],
    url: '/sites/{site}/logo',
} satisfies RouteDefinition<['post']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadLogo
 * @see app/Http/Controllers/SiteMediaController.php:41
 * @route '/sites/{site}/logo'
 */
uploadLogo.url = (
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
        uploadLogo.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadLogo
 * @see app/Http/Controllers/SiteMediaController.php:41
 * @route '/sites/{site}/logo'
 */
uploadLogo.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'post'> => ({
    url: uploadLogo.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadLogo
 * @see app/Http/Controllers/SiteMediaController.php:41
 * @route '/sites/{site}/logo'
 */
const uploadLogoForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: uploadLogo.url(args, options),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::uploadLogo
 * @see app/Http/Controllers/SiteMediaController.php:41
 * @route '/sites/{site}/logo'
 */
uploadLogoForm.post = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: uploadLogo.url(args, options),
    method: 'post',
});

uploadLogo.form = uploadLogoForm;

/**
 * @see \App\Http\Controllers\SiteMediaController::clearLogo
 * @see app/Http/Controllers/SiteMediaController.php:51
 * @route '/sites/{site}/logo'
 */
export const clearLogo = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: clearLogo.url(args, options),
    method: 'delete',
});

clearLogo.definition = {
    methods: ['delete'],
    url: '/sites/{site}/logo',
} satisfies RouteDefinition<['delete']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::clearLogo
 * @see app/Http/Controllers/SiteMediaController.php:51
 * @route '/sites/{site}/logo'
 */
clearLogo.url = (
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
        clearLogo.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::clearLogo
 * @see app/Http/Controllers/SiteMediaController.php:51
 * @route '/sites/{site}/logo'
 */
clearLogo.delete = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteDefinition<'delete'> => ({
    url: clearLogo.url(args, options),
    method: 'delete',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::clearLogo
 * @see app/Http/Controllers/SiteMediaController.php:51
 * @route '/sites/{site}/logo'
 */
const clearLogoForm = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: clearLogo.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::clearLogo
 * @see app/Http/Controllers/SiteMediaController.php:51
 * @route '/sites/{site}/logo'
 */
clearLogoForm.delete = (
    args: { site: string | number } | [site: string | number] | string | number,
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: clearLogo.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'DELETE',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

clearLogo.form = clearLogoForm;

/**
 * @see \App\Http\Controllers\SiteMediaController::updateAltText
 * @see app/Http/Controllers/SiteMediaController.php:58
 * @route '/sites/{site}/media/{mediaAsset}'
 */
export const updateAltText = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: updateAltText.url(args, options),
    method: 'patch',
});

updateAltText.definition = {
    methods: ['patch'],
    url: '/sites/{site}/media/{mediaAsset}',
} satisfies RouteDefinition<['patch']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::updateAltText
 * @see app/Http/Controllers/SiteMediaController.php:58
 * @route '/sites/{site}/media/{mediaAsset}'
 */
updateAltText.url = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            mediaAsset: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        mediaAsset: args.mediaAsset,
    };

    return (
        updateAltText.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{mediaAsset}', parsedArgs.mediaAsset.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::updateAltText
 * @see app/Http/Controllers/SiteMediaController.php:58
 * @route '/sites/{site}/media/{mediaAsset}'
 */
updateAltText.patch = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'patch'> => ({
    url: updateAltText.url(args, options),
    method: 'patch',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::updateAltText
 * @see app/Http/Controllers/SiteMediaController.php:58
 * @route '/sites/{site}/media/{mediaAsset}'
 */
const updateAltTextForm = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: updateAltText.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::updateAltText
 * @see app/Http/Controllers/SiteMediaController.php:58
 * @route '/sites/{site}/media/{mediaAsset}'
 */
updateAltTextForm.patch = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'post'> => ({
    action: updateAltText.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'PATCH',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'post',
});

updateAltText.form = updateAltTextForm;

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:65
 * @route '/sites/{site}/media/{mediaAsset}'
 */
export const show = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

show.definition = {
    methods: ['get', 'head'],
    url: '/sites/{site}/media/{mediaAsset}',
} satisfies RouteDefinition<['get', 'head']>;

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:65
 * @route '/sites/{site}/media/{mediaAsset}'
 */
show.url = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
) => {
    if (Array.isArray(args)) {
        args = {
            site: args[0],
            mediaAsset: args[1],
        };
    }

    args = applyUrlDefaults(args);

    const parsedArgs = {
        site: args.site,
        mediaAsset: args.mediaAsset,
    };

    return (
        show.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace('{mediaAsset}', parsedArgs.mediaAsset.toString())
            .replace(/\/+$/, '') + queryParams(options)
    );
};

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:65
 * @route '/sites/{site}/media/{mediaAsset}'
 */
show.get = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:65
 * @route '/sites/{site}/media/{mediaAsset}'
 */
show.head = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:65
 * @route '/sites/{site}/media/{mediaAsset}'
 */
const showForm = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:65
 * @route '/sites/{site}/media/{mediaAsset}'
 */
showForm.get = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, options),
    method: 'get',
});

/**
 * @see \App\Http\Controllers\SiteMediaController::show
 * @see app/Http/Controllers/SiteMediaController.php:65
 * @route '/sites/{site}/media/{mediaAsset}'
 */
showForm.head = (
    args:
        | { site: string | number; mediaAsset: string | number }
        | [site: string | number, mediaAsset: string | number],
    options?: RouteQueryOptions,
): RouteFormDefinition<'get'> => ({
    action: show.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        },
    }),
    method: 'get',
});

show.form = showForm;

const SiteMediaController = {
    uploadBlockImage,
    uploadLogo,
    clearLogo,
    updateAltText,
    show,
};

export default SiteMediaController;
