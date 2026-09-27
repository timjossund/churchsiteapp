<?php

namespace App\Http\Controllers;

use App\Actions\CancelSiteRenewal;
use App\Actions\OpenSiteBillingPortal;
use App\Actions\StartSiteCheckout;
use App\Exceptions\SiteBillingUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response;

class SiteBillingController extends Controller
{
    public function portal(Request $request, int $site, OpenSiteBillingPortal $portal): Response
    {
        try {
            $url = $portal->handle($request->user(), $site);
        } catch (ApiErrorException|SiteBillingUnavailable) {
            return back()->withErrors(['billing' => 'Billing management is temporarily unavailable. Please try again later.']);
        }
        abort_unless(str_starts_with($url, 'https://billing.stripe.com/'), 502);

        return Inertia::location($url);
    }

    public function cancel(Request $request, int $site, CancelSiteRenewal $cancel): RedirectResponse
    {
        try {
            $cancel->handle($request->user(), $site);
        } catch (ApiErrorException|SiteBillingUnavailable) {
            return back()->withErrors(['billing' => 'Renewal cancellation could not be confirmed. Please retry to confirm its status.']);
        }

        return to_route('sites.go-live', $site);
    }

    public function checkout(Request $request, int $site, StartSiteCheckout $checkout): RedirectResponse|Response
    {
        $owner = $request->user();
        $owner->sites()->findOrFail($site);
        abort_unless(config('site-billing.checkout_enabled') === true && config('customer-domains.enabled') === true, 404);
        $data = $request->validate(['interval' => ['required', Rule::in(['monthly', 'annual'])]]);

        try {
            $session = $checkout->handle($owner, $site, $data['interval']);
        } catch (ApiErrorException|SiteBillingUnavailable) {
            return back()->withErrors(['billing' => 'Billing is temporarily unavailable. Please retry to continue the same checkout.']);
        }

        abort_unless(is_string($session->url) && str_starts_with($session->url, 'https://checkout.stripe.com/'), 502);

        return Inertia::location($session->url);
    }
}
