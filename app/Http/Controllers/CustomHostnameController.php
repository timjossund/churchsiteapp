<?php

namespace App\Http\Controllers;

use App\Actions\DisconnectCustomHostname;
use App\Actions\ReconcileCustomHostname;
use App\Actions\ReserveCustomHostname;
use App\Actions\StartSiteCheckout;
use App\Exceptions\SiteBillingUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

class CustomHostnameController extends Controller
{
    public function store(Request $request, int $site, ReserveCustomHostname $reserve, StartSiteCheckout $checkout): Response
    {
        abort_unless(config('customer-domains.enabled') === true, 404);
        $owner = $request->user();
        $ownedSite = $owner->sites()->findOrFail($site);
        $reserve->handle($owner, $site, $request->input('hostname'));
        if ($ownedSite->hasPaidDomainAccess()) {
            return to_route('sites.go-live', $site);
        }
        abort_unless(config('site-billing.checkout_enabled') === true, 404);
        $data = $request->validate(['interval' => ['required', Rule::in(['monthly', 'annual'])]]);
        try {
            $session = $checkout->handle($owner, $site, $data['interval']);
        } catch (ApiErrorException|SiteBillingUnavailable) {
            return back()->withErrors(['billing' => 'Billing is temporarily unavailable. Please retry to continue the same checkout.']);
        }
        abort_unless(is_string($session->url) && str_starts_with($session->url, 'https://checkout.stripe.com/'), 502);

        return Inertia::location($session->url);
    }

    public function check(Request $request, int $site, ReconcileCustomHostname $reconcile): RedirectResponse
    {
        abort_unless(config('customer-domains.enabled') === true, 404);
        $domain = $request->user()->sites()->findOrFail($site)->customHostname()->firstOrFail();
        $reconcile->handle($domain->id);

        return to_route('sites.go-live', $site);
    }

    public function destroy(Request $request, int $site, DisconnectCustomHostname $disconnect): RedirectResponse
    {
        abort_unless(config('customer-domains.enabled') === true, 404);
        $disconnect->handle($request->user(), $site);

        return to_route('sites.go-live', $site);
    }
}
