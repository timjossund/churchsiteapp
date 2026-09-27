<?php

use App\Actions\DeleteAccountWhenBillingEnds;
use App\Actions\ReconcileCustomHostname;
use App\Models\CustomHostname;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('accounts:finalize-deletions', function (DeleteAccountWhenBillingEnds $deletion): void {
    foreach (User::query()->whereNotNull('deletion_requested_at')->lazyById() as $user) {
        $deletion->finalize($user->id);
    }
})->purpose('Reconcile cancellation and delete accounts after all billing commitments end');

Schedule::command('accounts:finalize-deletions')->hourly()->withoutOverlapping();

Artisan::command('domains:reconcile', function (ReconcileCustomHostname $reconcile): void {
    if (config('customer-domains.enabled') !== true) {
        return;
    }
    // Oldest checks first prevents later IDs starving. Bound both selection and runtime.
    $deadline = microtime(true) + 240;
    $ids = CustomHostname::query()->orderBy('last_checked_at')->orderBy('id')->limit(50)->pluck('id');
    foreach ($ids->chunk(10) as $chunk) {
        foreach ($chunk as $id) {
            if (microtime(true) >= $deadline) {
                return;
            }
            $reconcile->handle($id);
        }
    }
})->purpose('Reconcile customer hostname provisioning, DNS readiness, and removal');

Schedule::command('domains:reconcile')->everyFiveMinutes()->withoutOverlapping(10);
