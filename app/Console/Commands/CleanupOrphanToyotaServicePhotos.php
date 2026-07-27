<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\ToyotaServiceBookingPhoto;
use App\Support\Enums\AssetStatus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupOrphanToyotaServicePhotos extends Command
{
    protected $signature = 'assets:cleanup-orphan-toyota-service-photos
        {--grace-days=7 : Minimum age before an unattached upload is abandoned}
        {--hard-delete-after-days=30 : Delay before physical deletion}
        {--chunk=200 : Number of candidates per chunk}';

    protected $description = 'Safely soft-delete protected Toyota service photos that were never attached';

    public function handle(): int
    {
        $graceDays = max(1, (int) $this->option('grace-days'));
        $hardDeleteAfterDays = max(1, (int) $this->option('hard-delete-after-days'));
        $chunk = max(1, (int) $this->option('chunk'));
        $cutoff = now()->subDays($graceDays);
        $processed = 0;

        Asset::query()
            ->where('category', 'toyota-service-photo')
            ->where('status', AssetStatus::Active)
            ->where('is_protected', true)
            ->where('created_at', '<=', $cutoff)
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('toyota_service_booking_photos')
                ->whereColumn('toyota_service_booking_photos.asset_id', 'assets.id'))
            ->chunkById($chunk, function (Collection $assets) use (
                $cutoff,
                $hardDeleteAfterDays,
                &$processed,
            ): void {
                /** @var Collection<int, Asset> $assets */
                foreach ($assets as $candidate) {
                    DB::transaction(function () use (
                        $candidate,
                        $cutoff,
                        $hardDeleteAfterDays,
                        &$processed,
                    ): void {
                        /** @var Asset|null $asset */
                        $asset = Asset::query()
                            ->lockForUpdate()
                            ->find($candidate->getKey());
                        if (
                            $asset === null
                            || $asset->category !== 'toyota-service-photo'
                            || $asset->status !== AssetStatus::Active
                            || ! $asset->is_protected
                            || $asset->created_at?->greaterThan($cutoff)
                            || ToyotaServiceBookingPhoto::query()
                                ->where('asset_id', $asset->getKey())
                                ->exists()
                        ) {
                            return;
                        }

                        // The row lock serializes this recheck with booking creation.
                        $asset->is_protected = false;
                        $asset->save();
                        if ($asset->markAsSoftDeleted($hardDeleteAfterDays)) {
                            $processed++;
                        }
                    }, 3);
                }
            });

        $this->info("Soft-deleted {$processed} orphan Toyota service photo(s).");
        Log::info('assets:cleanup-orphan-toyota-service-photos completed', [
            'processed' => $processed,
            'grace_days' => $graceDays,
            'hard_delete_after_days' => $hardDeleteAfterDays,
        ]);

        return self::SUCCESS;
    }
}
