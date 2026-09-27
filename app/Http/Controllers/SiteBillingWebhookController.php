<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class SiteBillingWebhookController extends WebhookController
{
    public function __construct() {}

    public function handleWebhook(Request $request): Response
    {
        $secret = config('cashier.webhook.secret');
        abort_unless(is_string($secret) && $secret !== '', 503);

        try {
            $event = Webhook::constructEvent($request->getContent(), $request->header('Stripe-Signature', ''), $secret);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid webhook', 400);
        }

        $object = $event->data->object->toArray();
        $type = $event->type;
        $customer = $type === 'customer.deleted' ? ($object['id'] ?? null) : ($object['customer'] ?? null);
        if (! is_string($customer) || ! $site = Site::query()->where('stripe_id', $customer)->first()) {
            return response('', 200);
        }

        $subscriptionId = match (true) {
            in_array($type, ['customer.subscription.created', 'customer.subscription.updated', 'customer.subscription.deleted'], true) => $object['id'] ?? null,
            in_array($type, ['invoice.paid', 'invoice.payment_succeeded', 'invoice.payment_failed', 'invoice.payment_action_required'], true) => $object['parent']['subscription_details']['subscription'] ?? null,
            in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed'], true) => $object['subscription'] ?? null,
            default => null,
        };
        if (! is_string($subscriptionId) && $type !== 'customer.deleted') {
            return response('', 200);
        }

        try {
            DB::transaction(function () use ($site, $subscriptionId, $type, $customer): void {
                User::query()->lockForUpdate()->findOrFail($site->user_id);
                $site = Site::query()->lockForUpdate()->findOrFail($site->id);
                if ($site->stripe_id !== $customer) {
                    return;
                }
                if ($type === 'customer.deleted') {
                    $remote = Cashier::stripe()->customers->retrieve($customer);
                    if ($remote->deleted ?? false) {
                        $site->subscriptions()->update(['stripe_status' => 'canceled', 'ends_at' => now(), 'paid_until' => null]);
                    }

                    return;
                }

                // Read authoritative state under the same site lock; old events cannot restore old access.
                $remote = Cashier::stripe()->subscriptions->retrieve($subscriptionId, ['expand' => ['latest_invoice']]);
                if ($remote->customer !== $customer || $remote->id !== $subscriptionId) {
                    return;
                }
                $data = $remote->toArray();
                $paidUntil = $this->paidUntil($data);
                if (($data['cancel_at_period_end'] ?? false) === true) {
                    // Cashier can use the freshly fetched period end without a second Stripe request.
                    $data['cancel_at_period_end'] = false;
                    $data['cancel_at'] = $data['cancel_at'] ?? ($data['items']['data'][0]['current_period_end'] ?? null);
                }
                parent::handleCustomerSubscriptionUpdated(['data' => ['object' => $data]]);
                $site->subscriptions()->where('stripe_id', $subscriptionId)->update(['paid_until' => $paidUntil]);
            });
        } catch (ApiErrorException) {
            // Ask Stripe to retry; never acknowledge a failed reconciliation as successful.
            return response('Billing synchronization unavailable', 503);
        }

        return response('', 200);
    }

    /** @param array<string, mixed> $subscription */
    private function paidUntil(array $subscription): ?Carbon
    {
        $items = $subscription['items']['data'] ?? [];
        $invoice = $subscription['latest_invoice'] ?? null;
        if ($subscription['status'] !== 'active' || count($items) !== 1 || ! is_array($invoice)
            || ($invoice['status'] ?? null) !== 'paid' || ($invoice['amount_paid'] ?? 0) <= 0
            || ($invoice['currency'] ?? null) !== 'usd'
            || ($invoice['parent']['subscription_details']['subscription'] ?? null) !== $subscription['id']) {
            return null;
        }
        $item = $items[0];
        $price = $item['price'];
        $monthly = $price['id'] === config('site-billing.prices.monthly') && ($price['unit_amount'] ?? null) === 1500 && ($price['recurring']['interval'] ?? null) === 'month';
        $annual = $price['id'] === config('site-billing.prices.annual') && ($price['unit_amount'] ?? null) === 15000 && ($price['recurring']['interval'] ?? null) === 'year';
        if ((! $monthly && ! $annual) || ($price['currency'] ?? null) !== 'usd'
            || ($item['quantity'] ?? null) !== 1 || ($price['recurring']['interval_count'] ?? null) !== 1
            || ($price['recurring']['usage_type'] ?? null) !== 'licensed') {
            return null;
        }
        foreach ($invoice['lines']['data'] ?? [] as $line) {
            if (($line['parent']['subscription_item_details']['subscription_item'] ?? null) === $item['id']
                && ($line['pricing']['price_details']['price'] ?? null) === $price['id']
                && is_int($line['period']['end'] ?? null) && is_int($item['current_period_end'] ?? null)) {
                return Carbon::createFromTimestamp(min($line['period']['end'], $item['current_period_end']));
            }
        }

        return null;
    }
}
