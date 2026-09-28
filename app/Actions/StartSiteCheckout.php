<?php

namespace App\Actions;

use App\Models\Site;
use App\Models\User;
use App\Rules\CustomerHostname;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Stripe\Checkout\Session;

class StartSiteCheckout
{
    public function handle(User $owner, int $siteId, string $interval): Session
    {
        // Reserve the identity before any remote write, so a failed response can be retried safely.
        DB::transaction(function () use ($owner, $siteId, $interval): void {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $site = $owner->sites()->whereNull('deletion_requested_at')->lockForUpdate()->findOrFail($siteId);
            $this->guard($owner, $site);
            $price = app(ValidateSiteBillingPrice::class)->handle($interval);

            if ($site->checkout_session_id !== null) {
                $session = Cashier::stripe()->checkout->sessions->retrieve($site->checkout_session_id);
                $replace = $session->status === 'expired';
                if ($session->status === 'complete') {
                    if ($session->customer !== $site->stripe_id || ! is_string($session->subscription)) {
                        throw ValidationException::withMessages(['billing' => 'The previous checkout still needs billing reconciliation.']);
                    }
                    $previous = Cashier::stripe()->subscriptions->retrieve($session->subscription);
                    $replace = $previous->id === $session->subscription
                        && $previous->customer === $site->stripe_id
                        && in_array($previous->status, ['canceled', 'incomplete_expired'], true);
                    if (! $replace) {
                        throw ValidationException::withMessages(['billing' => 'The previous subscription has not been confirmed ended.']);
                    }
                }
                if ($replace) {
                    $site->forceFill(['checkout_attempt' => null, 'checkout_started_at' => null, 'checkout_price_id' => null, 'checkout_session_id' => null])->save();
                }
            }

            if ($site->checkout_attempt === null) {
                $site->forceFill([
                    'checkout_attempt' => (string) Str::uuid(),
                    'checkout_started_at' => now(),
                    'checkout_price_id' => $price->id,
                ])->save();
            } elseif ($site->checkout_price_id !== $price->id) {
                throw ValidationException::withMessages(['interval' => 'Finish or expire the existing checkout before choosing a different interval.']);
            }
        });

        // Commit the customer mapping separately so a later Checkout failure cannot orphan webhooks.
        DB::transaction(function () use ($owner, $siteId): void {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $site = $owner->sites()->whereNull('deletion_requested_at')->lockForUpdate()->findOrFail($siteId);
            $this->guard($owner, $site);
            if ($site->stripe_id === null) {
                $this->guardAttemptAge($site);
                $site->createAsStripeCustomer([], ['idempotency_key' => 'site-customer-'.$site->checkout_attempt]);
            }
        });

        return DB::transaction(function () use ($owner, $siteId): Session {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $site = $owner->sites()->whereNull('deletion_requested_at')->lockForUpdate()->findOrFail($siteId);
            $this->guard($owner, $site);
            $stripe = Cashier::stripe();

            if ($site->checkout_session_id !== null) {
                $session = $stripe->checkout->sessions->retrieve($site->checkout_session_id);
                if ($session->status !== 'open') {
                    throw ValidationException::withMessages(['billing' => 'This checkout has finished or expired. Billing must be reconciled before starting another.']);
                }

                return $session;
            }

            // Stripe only guarantees idempotency retention for 24 hours. Never replay an uncertain old attempt.
            $this->guardAttemptAge($site);
            $existing = $stripe->subscriptions->all(['customer' => $site->stripe_id, 'status' => 'all', 'limit' => 100]);
            if ($existing->has_more) {
                throw ValidationException::withMessages(['billing' => 'Billing history needs reconciliation before checkout.']);
            }
            // Check remote commitments as well as local webhook state before creating a session.
            foreach ($existing->data as $subscription) {
                if (! in_array($subscription->status, ['canceled', 'incomplete_expired'], true)) {
                    throw ValidationException::withMessages(['billing' => 'This site already has a subscription or pending payment.']);
                }
            }

            $session = $stripe->checkout->sessions->create([
                'customer' => $site->stripe_id,
                'mode' => 'subscription',
                // Keep standard Billing checkout independent of account-level Managed Payments defaults.
                'managed_payments' => ['enabled' => false],
                'line_items' => [['price' => $site->checkout_price_id, 'quantity' => 1]],
                'subscription_data' => ['metadata' => ['type' => 'default']],
                'success_url' => route('sites.go-live', $site).'?billing=processing',
                'cancel_url' => route('sites.go-live', $site).'?billing=canceled',
            ], ['idempotency_key' => 'site-checkout-'.$site->checkout_attempt]);
            $site->forceFill(['checkout_session_id' => $session->id])->save();

            return $session;
        });
    }

    private function guardAttemptAge(Site $site): void
    {
        if ($site->checkout_started_at === null || $site->checkout_started_at->lte(now()->subHours(23))) {
            throw ValidationException::withMessages(['billing' => 'The previous checkout needs reconciliation before it can be retried.']);
        }
    }

    private function guard(User $owner, Site $site): void
    {
        $domain = $site->customHostname()->first();
        if ($domain === null || $domain->state === 'removing'
            || Validator::make(['hostname' => $domain->hostname], ['hostname' => ['required', new CustomerHostname]])->fails()) {
            throw ValidationException::withMessages(['hostname' => 'Save a valid hostname before starting checkout.']);
        }
        if ($owner->getAttribute('deletion_requested_at') !== null
            || $site->subscriptions()->whereNotIn('stripe_status', ['canceled', 'incomplete_expired'])->exists()) {
            throw ValidationException::withMessages(['billing' => 'New checkout is unavailable while a subscription or account deletion is pending.']);
        }
    }
}
