<?php

namespace App\Actions;

use App\Exceptions\AccountBillingPending;
use App\Models\CustomHostname;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;

class DeleteAccountWhenBillingEnds
{
    public function request(User $user): bool
    {
        // Persist the request before Stripe calls so failures cannot permit a new checkout.
        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            if ($user->deletion_requested_at === null) {
                $user->forceFill(['deletion_requested_at' => now(), 'deletion_scheduled_for' => null])->save();
            }
        });

        return $this->finalize($user->id);
    }

    public function finalize(int $userId): bool
    {
        $user = User::query()->find($userId);
        if ($user === null) {
            return true;
        }
        if ($user->deletion_requested_at === null) {
            return false;
        }
        // Site deletion may still own file or provider identities that a cascade would erase.
        foreach ($user->sites()->whereNotNull('deletion_requested_at')->pluck('id') as $siteId) {
            app(FinalizeSiteDeletion::class)->handle($siteId);
        }

        try {
            $ready = DB::transaction(function () use ($userId): bool {
                $user = User::query()->lockForUpdate()->find($userId);
                if ($user === null) {
                    return true;
                }
                if ($user->deletion_requested_at === null) {
                    return false;
                }

                $latestEnd = null;
                $hasCommitment = false;
                $unresolved = false;
                foreach ($user->sites()->orderBy('id')->lockForUpdate()->get() as $site) {
                    try {
                        [$end, $committed] = app(CancelSiteBillingCommitments::class)->handle($site);
                    } catch (ApiErrorException|AccountBillingPending) {
                        $unresolved = true;

                        continue;
                    }
                    $hasCommitment = $hasCommitment || $committed;
                    if ($end !== null && ($latestEnd === null || $end->gt($latestEnd))) {
                        $latestEnd = $end;
                    }
                }

                if ($unresolved) {
                    $user->forceFill(['deletion_scheduled_for' => null])->save();

                    return false;
                }

                if ($hasCommitment || ($latestEnd !== null && $latestEnd->isFuture())) {
                    $user->forceFill(['deletion_scheduled_for' => $latestEnd])->save();

                    return false;
                }

                foreach ($user->sites()->orderBy('id')->get() as $site) {
                    $site->customHostname()->update(['state' => 'removing', 'verified_at' => null, 'cname_matches' => false]);
                }

                return true;
            });
            if (! $ready) {
                return false;
            }

            // Commit routing revocation before remote cleanup. Keep IDs and the account on failure.
            foreach (CustomHostname::query()->whereHas('site', fn ($query) => $query->where('user_id', $userId))->pluck('id') as $id) {
                app(ReconcileCustomHostname::class)->handle($id);
            }

            return DB::transaction(function () use ($userId): bool {
                $user = User::query()->lockForUpdate()->find($userId);
                if ($user === null) {
                    return true;
                }
                if ($user->deletion_requested_at === null) {
                    return false;
                }
                foreach ($user->sites()->orderBy('id')->lockForUpdate()->get() as $site) {
                    if ($site->deletion_requested_at !== null
                        || $site->customHostname()->exists()
                        || $site->subscriptions()->where('paid_until', '>', now())->exists()) {
                        return false;
                    }
                }
                $user->delete();

                return true;
            });
        } catch (ApiErrorException|AccountBillingPending) {
            // External cancellations may have succeeded. Keep the request and safely reconcile next time.
            User::query()->whereKey($userId)->whereNotNull('deletion_requested_at')->update(['deletion_scheduled_for' => null]);

            return false;
        }
    }
}
