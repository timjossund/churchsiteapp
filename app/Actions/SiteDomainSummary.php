<?php

namespace App\Actions;

use App\Models\Site;

class SiteDomainSummary
{
    /** @return array<string, mixed> */
    public function handle(Site $site): array
    {
        $domain = $site->customHostname()->first();
        $paid = $site->hasPaidDomainAccess();
        $enabled = config('customer-domains.enabled') === true;
        $deleting = $site->user->deletion_requested_at !== null;
        $subscription = $site->billingSubscription();
        $pendingPayment = $site->checkout_attempt !== null && ($subscription === null
            || ($subscription->getAttribute('paid_until') === null && in_array($subscription->stripe_status, ['active', 'incomplete'], true)));
        $commitment = $site->subscriptions()->whereNotIn('stripe_status', ['canceled', 'incomplete_expired'])->exists();
        $interval = $site->checkout_price_id === null ? null : match ($site->checkout_price_id) {
            config('site-billing.prices.monthly') => 'monthly',
            config('site-billing.prices.annual') => 'annual',
            default => null,
        };
        $status = match (true) {
            $domain === null => 'empty',
            $domain->state === 'removing' => 'removal_pending',
            ! $enabled => 'paused',
            $domain->error_category !== null => 'unavailable',
            ! $paid => $pendingPayment ? 'payment_pending' : 'unpaid',
            $domain->verified_at === null || ! $domain->cname_matches => 'dns_pending',
            $domain->hostname_status !== 'active' => 'connection_pending',
            $domain->ssl_status !== 'active' => 'ssl_pending',
            $domain->state !== 'ready' || $domain->cloudflare_id === null => 'checking',
            $site->published_at === null => 'unpublished',
            default => 'live',
        };
        $message = match ($domain?->error_category) {
            'operator_required' => 'This connection needs support review. Your hostname remains reserved; contact support before replacing it.',
            'configuration' => 'Domain setup is temporarily unavailable. Please contact support.',
            'rate_limited' => 'The provider is busy. Automatic checks will retry; please wait before refreshing.',
            null => null,
            default => 'We could not confirm the connection. Your settings are saved. Refresh to retry.',
        };
        $records = $domain === null ? [] : array_merge([
            ['purpose' => 'ownership', 'type' => 'TXT', 'name' => $domain->ownershipRecordName(), 'value' => $domain->ownership_challenge],
            ['purpose' => 'connection', 'type' => 'CNAME', 'name' => $domain->hostname, 'value' => config('customer-domains.cname_target')],
        ], $domain->dns_instructions ?? []);

        return [
            'enabled' => $enabled,
            'hostname' => $domain?->hostname,
            'status' => $status,
            'message' => $message,
            'paid' => $paid,
            'published' => $site->published_at !== null,
            'ownership_verified' => $domain?->verified_at !== null,
            'dns_connected' => $domain->cname_matches ?? false,
            'connection_ready' => $domain?->hostname_status === 'active',
            'ssl_ready' => $domain?->ssl_status === 'active',
            'last_checked_at' => $domain?->last_checked_at?->toIso8601String(),
            'records' => $records,
            'checkout_interval' => $interval,
            'can_connect' => $enabled && ! $deleting && $domain === null,
            'can_checkout' => $enabled && config('site-billing.checkout_enabled') === true && ! $deleting && ! $paid && ! $commitment && $domain?->state !== 'removing',
            'can_check' => $enabled && $domain !== null,
            'can_disconnect' => $enabled && $domain !== null && $domain->state !== 'removing',
            'poll' => $enabled && $domain !== null && ! in_array($status, ['live', 'unpublished', 'unpaid'], true)
                && ! in_array($domain->error_category, ['operator_required', 'configuration'], true),
        ];
    }
}
