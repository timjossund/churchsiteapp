<?php

namespace App\Actions;

use App\Models\Site;
use Carbon\CarbonImmutable;

class SiteBillingSummary
{
    /** @return array{status: string, interval: ?string, amount: ?int, currency: string, paid_until: ?string, ends_at: ?string, can_manage: bool, can_cancel: bool} */
    public function handle(Site $site): array
    {
        $subscription = $site->billingSubscription();
        $summary = [
            'status' => $site->checkout_attempt === null ? 'free' : 'pending',
            'interval' => null,
            'amount' => null,
            'currency' => 'USD',
            'paid_until' => null,
            'ends_at' => null,
            'can_manage' => false,
            'can_cancel' => false,
        ];
        if ($subscription === null) {
            return $summary;
        }

        $summary['can_manage'] = $site->stripe_id !== null;
        $summary['can_cancel'] = $site->stripe_id !== null && $site->user?->deletion_requested_at === null
            && $subscription->ends_at === null
            && ! in_array($subscription->stripe_status, ['canceled', 'incomplete_expired'], true);
        $priceId = $subscription->stripe_price;
        if (is_string($priceId) && $priceId !== '') {
            if ($priceId === config('site-billing.prices.monthly')) {
                $summary['interval'] = 'monthly';
                $summary['amount'] = 1500;
            } elseif ($priceId === config('site-billing.prices.annual')) {
                $summary['interval'] = 'annual';
                $summary['amount'] = 15000;
            }
        }
        $paidUntil = $subscription->getAttribute('paid_until');
        $summary['paid_until'] = is_string($paidUntil) ? CarbonImmutable::parse($paidUntil, 'UTC')->toIso8601String() : null;
        $summary['ends_at'] = $subscription->ends_at?->toIso8601String();
        $summary['status'] = match ($subscription->stripe_status) {
            'canceled', 'incomplete_expired' => 'ended',
            'past_due', 'unpaid', 'incomplete' => 'action_required',
            'active' => $site->hasPaidDomainAccess()
                ? ($subscription->ends_at === null ? 'active' : 'cancellation_scheduled')
                : 'pending',
            default => 'unavailable',
        };

        return $summary;
    }
}
