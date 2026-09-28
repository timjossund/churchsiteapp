<?php

namespace App\Actions;

use App\Exceptions\AccountBillingPending;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FilesystemException;
use Stripe\Exception\ApiErrorException;

class FinalizeSiteDeletion
{
    public function handle(int $siteId): bool
    {
        $snapshot = Site::query()->find($siteId);
        if ($snapshot === null) {
            return true;
        }
        if ($snapshot->deletion_requested_at === null) {
            return false;
        }

        $billingReady = DB::transaction(function () use ($snapshot): bool {
            User::query()->lockForUpdate()->find($snapshot->user_id);
            $site = Site::query()->lockForUpdate()->find($snapshot->id);

            return $site === null || ($site->deletion_requested_at !== null && $this->billingReady($site));
        });

        // Content stays offline even while a paid period or an unavailable provider delays cleanup.
        $domainId = $snapshot->customHostname()->value('id');
        if ($domainId !== null) {
            app(ReconcileCustomHostname::class)->handle((int) $domainId);
        }
        foreach ($snapshot->mediaAssets()->pluck('id') as $assetId) {
            $this->removeAsset($snapshot, $assetId);
        }

        if (! $billingReady) {
            return false;
        }

        return DB::transaction(function () use ($snapshot): bool {
            User::query()->lockForUpdate()->find($snapshot->user_id);
            $site = Site::query()->lockForUpdate()->find($snapshot->id);
            if ($site === null) {
                return true;
            }
            if ($site->deletion_requested_at === null || $site->customHostname()->exists()
                || $site->mediaAssets()->exists()) {
                return false;
            }
            // Reconcile again under the final lock: a webhook may have arrived during cleanup.
            if (! $this->billingReady($site)) {
                return false;
            }
            $site->delete();

            return true;
        });
    }

    // Called under the user/site locks; expected failures are caught inside the transaction so
    // a recovered Stripe customer mapping is committed even if a later provider call fails.
    private function billingReady(Site $site): bool
    {
        try {
            [$end, $committed] = app(CancelSiteBillingCommitments::class)->handle($site);

            return ! $committed && ($end === null || ! $end->isFuture());
        } catch (ApiErrorException|AccountBillingPending) {
            Log::warning('Site deletion billing reconciliation is pending.', ['site_id' => $site->id]);

            return false;
        }
    }

    private function removeAsset(Site $snapshot, int $assetId): void
    {
        try {
            DB::transaction(function () use ($snapshot, $assetId): void {
                User::query()->lockForUpdate()->find($snapshot->user_id);
                $site = Site::query()->lockForUpdate()->find($snapshot->id);
                if ($site === null || $site->deletion_requested_at === null) {
                    return;
                }
                $asset = $site->mediaAssets()->whereKey($assetId)->lockForUpdate()->first();
                if ($asset === null) {
                    return;
                }
                $key = $asset->storage_key;
                if (! str_starts_with($key, "sites/{$site->id}/")
                    || $key === "sites/{$site->id}/"
                    || preg_match('#(?:^|/)\.\.?(?:/|$)|[\\\\\x00-\x1f]#', $key) === 1) {
                    Log::warning('Site deletion requires storage-key reconciliation.', ['site_id' => $site->id, 'media_asset_id' => $asset->id]);

                    return;
                }
                $disk = Storage::disk('s3');
                if ($disk->exists($key) && ! $disk->delete($key)) {
                    Log::warning('Site deletion file removal is pending.', ['site_id' => $site->id, 'media_asset_id' => $asset->id]);

                    return;
                }
                $asset->delete();
            });
        } catch (FilesystemException) {
            Log::warning('Site deletion storage reconciliation is pending.', ['site_id' => $snapshot->id, 'media_asset_id' => $assetId]);
        }
    }
}
