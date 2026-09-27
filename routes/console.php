<?php

use App\Actions\DeleteAccountWhenBillingEnds;
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
