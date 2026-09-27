<?php

namespace App\Actions;

use App\Exceptions\SiteBillingUnavailable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;

class CancelSiteRenewal
{
    public function handle(User $owner, int $siteId): void
    {
        DB::transaction(function () use ($owner, $siteId): void {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $site = $owner->sites()->lockForUpdate()->findOrFail($siteId);
            $subscription = $site->billingSubscription();
            abort_if($subscription === null, 404);
            $remote = Cashier::stripe()->subscriptions->retrieve($subscription->stripe_id);
            if ($remote->customer !== $site->stripe_id) {
                throw new SiteBillingUnavailable('The subscription customer could not be confirmed.');
            }
            if (in_array($remote->status, ['canceled', 'incomplete_expired'], true)) {
                $subscription->forceFill(['stripe_status' => $remote->status])->save();

                return;
            }
            if (! $remote->cancel_at_period_end) {
                $remote = Cashier::stripe()->subscriptions->update($remote->id, ['cancel_at_period_end' => true]);
            }
            if (! $remote->cancel_at_period_end) {
                throw new SiteBillingUnavailable('Cancellation has not been confirmed.');
            }
            $end = $remote->cancel_at;
            if ($end === null) {
                foreach ($remote->items->data as $item) {
                    $end = max($end ?? 0, $item->current_period_end);
                }
            }
            if ($end === null || $end <= 0) {
                throw new SiteBillingUnavailable('The subscription end date could not be confirmed.');
            }
            $subscription->forceFill(['stripe_status' => $remote->status, 'ends_at' => CarbonImmutable::createFromTimestamp($end)])->save();
        });
    }
}
