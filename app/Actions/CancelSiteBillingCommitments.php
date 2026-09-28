<?php

namespace App\Actions;

use App\Exceptions\AccountBillingPending;
use App\Models\Site;
use Carbon\CarbonImmutable;
use Laravel\Cashier\Cashier;

class CancelSiteBillingCommitments
{
    /** @return array{?CarbonImmutable, bool} */
    public function handle(Site $site): array
    {
        if ($site->stripe_id === null) {
            if ($site->checkout_attempt === null && ! $site->subscriptions()->exists()) {
                return [null, false];
            }
            if ($site->checkout_attempt === null || $site->checkout_started_at === null || $site->checkout_started_at->lte(now()->subHours(23))) {
                throw new AccountBillingPending('A customer response needs reconciliation before deletion.');
            }
            $site->createAsStripeCustomer([], ['idempotency_key' => 'site-customer-'.$site->checkout_attempt]);
        }

        $stripe = Cashier::stripe();
        $unpaidSubscriptions = [];
        $unresolvedPayment = false;
        // Expire all open sessions, including one whose creation response was lost locally.
        foreach ($stripe->checkout->sessions->all(['customer' => $site->stripe_id])->autoPagingIterator() as $session) {
            if ($session->status === 'open') {
                $stripe->checkout->sessions->expire($session->id);
            } elseif ($session->status === 'complete' && $session->payment_status === 'unpaid') {
                if (! is_string($session->subscription)) {
                    $unresolvedPayment = true;
                } else {
                    $unpaidSubscriptions[$session->subscription] = true;
                }
            }
        }

        $latestEnd = null;
        $hasCommitment = false;
        foreach ($stripe->subscriptions->all(['customer' => $site->stripe_id, 'status' => 'all'])->autoPagingIterator() as $subscription) {
            if ($subscription->customer !== $site->stripe_id) {
                throw new AccountBillingPending('Subscription customer mismatch.');
            }
            unset($unpaidSubscriptions[$subscription->id]);
            if (in_array($subscription->status, ['canceled', 'incomplete_expired'], true)) {
                continue;
            }
            $hasCommitment = true;
            if (! $subscription->cancel_at_period_end) {
                $subscription = $stripe->subscriptions->update($subscription->id, ['cancel_at_period_end' => true]);
            }
            if (! $subscription->cancel_at_period_end) {
                throw new AccountBillingPending('Subscription renewal cancellation is not confirmed.');
            }
            $end = $subscription->cancel_at;
            if (! is_int($end)) {
                foreach ($subscription->items->data as $item) {
                    $end = max($end ?? 0, $item->current_period_end);
                }
            }
            if (! is_int($end) || $end <= 0) {
                throw new AccountBillingPending('Subscription end is not confirmed.');
            }
            $date = CarbonImmutable::createFromTimestamp($end);
            if ($latestEnd === null || $date->gt($latestEnd)) {
                $latestEnd = $date;
            }
            $site->subscriptions()->where('stripe_id', $subscription->id)->update(['ends_at' => $date]);
        }

        if ($unresolvedPayment || $unpaidSubscriptions !== []) {
            throw new AccountBillingPending('A checkout subscription is still unresolved.');
        }

        // Retain already-confirmed paid time even if Stripe terminated a subscription early.
        $paidUntil = $site->subscriptions()->max('paid_until');
        if (is_string($paidUntil)) {
            $paidDate = CarbonImmutable::parse($paidUntil, 'UTC');
            if ($latestEnd === null || $paidDate->gt($latestEnd)) {
                $latestEnd = $paidDate;
            }
        }

        return [$latestEnd, $hasCommitment];
    }
}
