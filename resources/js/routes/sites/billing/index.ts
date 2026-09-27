import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\SiteBillingWebhookController::webhook
* @see app/Http/Controllers/SiteBillingWebhookController.php:22
* @route '/stripe/webhook'
*/
export const webhook = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: webhook.url(options),
    method: 'post',
})

webhook.definition = {
    methods: ["post"],
    url: '/stripe/webhook',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteBillingWebhookController::webhook
* @see app/Http/Controllers/SiteBillingWebhookController.php:22
* @route '/stripe/webhook'
*/
webhook.url = (options?: RouteQueryOptions) => {
    return webhook.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteBillingWebhookController::webhook
* @see app/Http/Controllers/SiteBillingWebhookController.php:22
* @route '/stripe/webhook'
*/
webhook.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: webhook.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBillingWebhookController::webhook
* @see app/Http/Controllers/SiteBillingWebhookController.php:22
* @route '/stripe/webhook'
*/
const webhookForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: webhook.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBillingWebhookController::webhook
* @see app/Http/Controllers/SiteBillingWebhookController.php:22
* @route '/stripe/webhook'
*/
webhookForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: webhook.url(options),
    method: 'post',
})

webhook.form = webhookForm

/**
* @see \App\Http\Controllers\SiteBillingController::portal
* @see app/Http/Controllers/SiteBillingController.php:18
* @route '/sites/{site}/billing/portal'
*/
export const portal = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: portal.url(args, options),
    method: 'post',
})

portal.definition = {
    methods: ["post"],
    url: '/sites/{site}/billing/portal',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteBillingController::portal
* @see app/Http/Controllers/SiteBillingController.php:18
* @route '/sites/{site}/billing/portal'
*/
portal.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return portal.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteBillingController::portal
* @see app/Http/Controllers/SiteBillingController.php:18
* @route '/sites/{site}/billing/portal'
*/
portal.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: portal.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBillingController::portal
* @see app/Http/Controllers/SiteBillingController.php:18
* @route '/sites/{site}/billing/portal'
*/
const portalForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: portal.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBillingController::portal
* @see app/Http/Controllers/SiteBillingController.php:18
* @route '/sites/{site}/billing/portal'
*/
portalForm.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: portal.url(args, options),
    method: 'post',
})

portal.form = portalForm

/**
* @see \App\Http\Controllers\SiteBillingController::cancel
* @see app/Http/Controllers/SiteBillingController.php:30
* @route '/sites/{site}/billing/cancel'
*/
export const cancel = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

cancel.definition = {
    methods: ["post"],
    url: '/sites/{site}/billing/cancel',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteBillingController::cancel
* @see app/Http/Controllers/SiteBillingController.php:30
* @route '/sites/{site}/billing/cancel'
*/
cancel.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return cancel.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteBillingController::cancel
* @see app/Http/Controllers/SiteBillingController.php:30
* @route '/sites/{site}/billing/cancel'
*/
cancel.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: cancel.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBillingController::cancel
* @see app/Http/Controllers/SiteBillingController.php:30
* @route '/sites/{site}/billing/cancel'
*/
const cancelForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancel.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBillingController::cancel
* @see app/Http/Controllers/SiteBillingController.php:30
* @route '/sites/{site}/billing/cancel'
*/
cancelForm.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: cancel.url(args, options),
    method: 'post',
})

cancel.form = cancelForm

/**
* @see \App\Http\Controllers\SiteBillingController::checkout
* @see app/Http/Controllers/SiteBillingController.php:41
* @route '/sites/{site}/billing/checkout'
*/
export const checkout = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: checkout.url(args, options),
    method: 'post',
})

checkout.definition = {
    methods: ["post"],
    url: '/sites/{site}/billing/checkout',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\SiteBillingController::checkout
* @see app/Http/Controllers/SiteBillingController.php:41
* @route '/sites/{site}/billing/checkout'
*/
checkout.url = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return checkout.definition.url
            .replace('{site}', parsedArgs.site.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\SiteBillingController::checkout
* @see app/Http/Controllers/SiteBillingController.php:41
* @route '/sites/{site}/billing/checkout'
*/
checkout.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: checkout.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBillingController::checkout
* @see app/Http/Controllers/SiteBillingController.php:41
* @route '/sites/{site}/billing/checkout'
*/
const checkoutForm = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: checkout.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\SiteBillingController::checkout
* @see app/Http/Controllers/SiteBillingController.php:41
* @route '/sites/{site}/billing/checkout'
*/
checkoutForm.post = (args: { site: string | number } | [site: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: checkout.url(args, options),
    method: 'post',
})

checkout.form = checkoutForm

const billing = {
    webhook: Object.assign(webhook, webhook),
    portal: Object.assign(portal, portal),
    cancel: Object.assign(cancel, cancel),
    checkout: Object.assign(checkout, checkout),
}

export default billing