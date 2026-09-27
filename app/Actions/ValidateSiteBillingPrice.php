<?php

namespace App\Actions;

use App\Exceptions\SiteBillingUnavailable;
use Illuminate\Validation\ValidationException;
use Laravel\Cashier\Cashier;
use Stripe\Price;

class ValidateSiteBillingPrice
{
    public function handle(string $interval): Price
    {
        [$amount, $period] = match ($interval) {
            'monthly' => [1500, 'month'],
            'annual' => [15000, 'year'],
            default => throw ValidationException::withMessages([
                'interval' => 'Choose monthly or annual billing.',
            ]),
        };

        $priceId = config('site-billing.prices.'.$interval);

        if (! is_string($priceId) || ! str_starts_with($priceId, 'price_') || trim($priceId) !== $priceId) {
            throw new SiteBillingUnavailable('Configure the '.$interval.' site subscription price before enabling checkout.');
        }

        $price = Cashier::stripe()->prices->retrieve($priceId);

        if ($price->id !== $priceId
            || $price->active !== true
            || $price->currency !== 'usd'
            || $price->unit_amount !== $amount
            || $price->type !== 'recurring'
            || $price->billing_scheme !== 'per_unit'
            || $price->transform_quantity !== null
            || $price->recurring?->interval !== $period
            || $price->recurring->interval_count !== 1
            || $price->recurring->usage_type !== 'licensed'
            || ($price->recurring->trial_period_days !== null && $price->recurring->trial_period_days !== 0)) {
            throw new SiteBillingUnavailable('The '.$interval.' Stripe price must match the approved USD site subscription amount and interval.');
        }

        return $price;
    }
}
