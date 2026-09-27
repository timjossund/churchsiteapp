<?php

namespace App\Actions;

use App\Exceptions\SiteBillingUnavailable;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Cashier;

class OpenSiteBillingPortal
{
    public function handle(User $owner, int $siteId): string
    {
        return DB::transaction(function () use ($owner, $siteId): string {
            $owner = User::query()->lockForUpdate()->findOrFail($owner->id);
            $site = $owner->sites()->lockForUpdate()->findOrFail($siteId);
            abort_unless($site->stripe_id !== null && $site->subscriptions()->exists(), 404);
            $id = config('site-billing.portal_configuration');
            if (! is_string($id) || ! str_starts_with($id, 'bpc_')) {
                throw new SiteBillingUnavailable('The restricted billing portal must be configured first.');
            }
            $configuration = Cashier::stripe()->billingPortal->configurations->retrieve($id);
            $features = $configuration->features;
            if ($configuration->id !== $id || $configuration->active !== true
                || $configuration->login_page->enabled !== false
                || $features->subscription_cancel->enabled !== false
                || $features->subscription_update->enabled !== false
                || $features->customer_update->enabled !== false
                || $features->payment_method_update->enabled !== true
                || $features->invoice_history->enabled !== true) {
                throw new SiteBillingUnavailable('The billing portal must allow only invoices and payment methods.');
            }

            return $site->billingPortalUrl(route('sites.show', $site), ['configuration' => $id]);
        });
    }
}
