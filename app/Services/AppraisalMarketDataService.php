<?php

namespace App\Services;

use App\Contracts\MarketDataProvider;
use App\Exceptions\AiAgentException;
use App\Exceptions\MarketDataProviderException;
use App\Exceptions\NoEligibleMarketDataSourceException;
use App\Jobs\ProcessAppraisalMarketData;
use App\Models\Appraisal;
use App\Models\AppraisalMarketComparable;
use App\Models\AppraisalMarketEstimate;
use App\Models\AppraisalStatusHistory;
use App\Models\MarketDataSource;
use App\Models\User;
use App\Services\MarketData\OlxApprovedHtmlProvider;
use App\Services\MarketData\OpenAiMarketResearchProvider;
use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalMarketEstimateStatus;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class AppraisalMarketDataService
{
    /** @var array<string, MarketDataProvider> */
    private array $providers;

    public function __construct(
        OlxApprovedHtmlProvider $olx,
        private readonly OpenAiMarketResearchProvider $aiFallback,
        private readonly AppraisalValuationEngine $engine,
        private readonly AppraisalAutomaticResultPublisher $resultPublisher,
    ) {
        $this->providers = [$olx->code() => $olx];
    }

    public function process(Appraisal $appraisal, bool $force = false): AppraisalMarketEstimate
    {
        $appraisal->loadMissing('vehicle');
        $existing = $appraisal->latestMarketEstimate()->first();
        if (
            ! $force
            && $existing !== null
            && $appraisal->submitted_at !== null
            && $existing->calculated_at->gte($appraisal->submitted_at)
        ) {
            if ($existing->status === AppraisalMarketEstimateStatus::Ready) {
                $this->resultPublisher->publish($appraisal, $existing);
            }

            return $existing->load('comparables');
        }

        $sources = MarketDataSource::query()
            ->whereIn('code', [
                ...array_keys($this->providers),
                $this->aiFallback->code(),
            ])
            ->get();
        $primarySources = $sources
            ->whereIn('code', array_keys($this->providers))
            ->filter(fn (MarketDataSource $source): bool => $source->isEligible());
        $fallbackSource = $sources
            ->firstWhere('code', $this->aiFallback->code());
        $fallbackIsEligible = (bool) config('appraisal.ai.enabled')
            && $fallbackSource instanceof MarketDataSource
            && $fallbackSource->isEligible();
        if ($primarySources->isEmpty() && ! $fallbackIsEligible) {
            throw new NoEligibleMarketDataSourceException(
                'Tidak ada provider market data berizin yang aktif.',
            );
        }

        $listings = [];
        $providerCodes = [];
        $lastException = null;
        $successfulProviderCount = 0;
        foreach ($primarySources as $source) {
            $provider = $this->providers[$source->code] ?? null;
            if ($provider === null) {
                continue;
            }

            $source->update(['last_synced_at' => now()]);
            try {
                $fetched = $provider->fetch($appraisal, $source);
                $listings = [...$listings, ...$fetched];
                $providerCodes[] = $source->code;
                $successfulProviderCount++;
                $source->update([
                    'last_success_at' => now(),
                    'last_error_code' => null,
                ]);
            } catch (Throwable $exception) {
                $lastException = $exception;
                $source->update([
                    'last_failure_at' => now(),
                    'last_error_code' => 'provider_fetch_failed',
                ]);
            }
        }

        $valuation = $this->engine->estimate($appraisal, $listings);
        $fallbackAudit = [
            'attempted' => false,
            'status' => $fallbackIsEligible ? 'not_needed' : 'not_eligible',
            'error_code' => null,
        ];
        if (
            $valuation['status'] !== AppraisalMarketEstimateStatus::Ready
            && $fallbackIsEligible
        ) {
            $fallbackAudit['attempted'] = true;
            $fallbackAudit['status'] = 'running';
            $fallbackSource->update(['last_synced_at' => now()]);
            try {
                $fetched = $this->aiFallback->fetch($appraisal, $fallbackSource);
                $reusable = $this->recentAcceptedAiComparables($appraisal);
                $listings = [...$listings, ...$reusable, ...$fetched];
                $providerCodes[] = $fallbackSource->code;
                $successfulProviderCount++;
                $fallbackAudit['status'] = 'completed';
                $fallbackAudit['reused_comparable_count'] = count($reusable);
                $fallbackSource->update([
                    'last_success_at' => now(),
                    'last_error_code' => null,
                ]);
                $valuation = $this->engine->estimate($appraisal, $listings);
            } catch (Throwable $exception) {
                $lastException = $exception;
                $fallbackAudit['status'] = 'failed';
                $fallbackAudit['error_code'] = $exception instanceof AiAgentException
                    ? $exception->errorCode
                    : 'ai_fallback_failed';
                $fallbackSource->update([
                    'last_failure_at' => now(),
                    'last_error_code' => $fallbackAudit['error_code'],
                ]);
            }
        }

        if ($successfulProviderCount === 0 && $lastException !== null) {
            throw new MarketDataProviderException(
                'Seluruh provider market data gagal diakses.',
                previous: $lastException,
            );
        }
        $valuation['calculation']['ai_fallback'] = $fallbackAudit;

        $estimate = DB::transaction(function () use (
            $appraisal,
            $valuation,
            $providerCodes,
        ): AppraisalMarketEstimate {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());
            $version = ((int) $locked->marketEstimates()->max('version')) + 1;
            $estimate = new AppraisalMarketEstimate([
                'version' => $version,
                'status' => $valuation['status'],
                'market_low' => $valuation['market_low'],
                'market_mid' => $valuation['market_mid'],
                'market_high' => $valuation['market_high'],
                'trade_in_low' => $valuation['trade_in_low'],
                'trade_in_high' => $valuation['trade_in_high'],
                'confidence' => $valuation['confidence'],
                'comparable_count' => $valuation['comparable_count'],
                'data_as_of' => $valuation['data_as_of'],
                'provider_codes' => array_values(array_unique($providerCodes)),
                'adjustments' => $valuation['adjustments'],
                'calculation' => $valuation['calculation'],
                'calculated_at' => now(),
            ]);
            $estimate->appraisal()->associate($locked);
            $estimate->save();

            foreach ($valuation['comparables'] as $payload) {
                $comparable = new AppraisalMarketComparable($payload);
                $comparable->estimate()->associate($estimate);
                $comparable->save();
            }

            $engineStatus = $valuation['status'] === AppraisalMarketEstimateStatus::Ready
                ? AppraisalStatus::AutoEstimated
                : AppraisalStatus::Failed;
            if (! in_array($locked->status, [
                AppraisalStatus::Submitted,
                AppraisalStatus::CollectingMarketData,
                AppraisalStatus::AutoEstimated,
                AppraisalStatus::InsufficientComparables,
                AppraisalStatus::UnderAppraiserReview,
                AppraisalStatus::Failed,
            ], true)) {
                return $estimate->load('comparables');
            }

            $locked->update(['status' => $engineStatus]);
            $this->history(
                $locked,
                $engineStatus,
                $engineStatus === AppraisalStatus::AutoEstimated
                    ? 'Estimasi otomatis selesai'
                    : 'Pemrosesan otomatis belum berhasil',
                $engineStatus === AppraisalStatus::AutoEstimated
                    ? 'Data OLX atau fallback OpenAI berhasil diolah oleh engine.'
                    : 'OLX dan fallback OpenAI belum menghasilkan data pembanding yang memadai.',
            );

            return $estimate->load('comparables');
        });

        if ($estimate->status === AppraisalMarketEstimateStatus::Ready) {
            $this->resultPublisher->publish($appraisal->refresh(), $estimate);
        }

        return $estimate;
    }

    /** @return list<array<string, mixed>> */
    private function recentAcceptedAiComparables(Appraisal $appraisal): array
    {
        $comparables = AppraisalMarketComparable::query()
            ->whereHas(
                'estimate',
                fn (Builder $query): Builder => $query
                    ->where('appraisal_id', $appraisal->getKey()),
            )
            ->where('source_code', $this->aiFallback->code())
            ->whereNull('exclusion_reason')
            ->where(
                'observed_at',
                '>=',
                now()->subDays(
                    (int) config('appraisal.market_data.maximum_age_days'),
                ),
            )
            ->latest('observed_at')
            ->limit(50)
            ->get();
        $listings = [];
        foreach ($comparables as $comparable) {
            $listings[] = [
                'market_data_source_id' => $comparable->market_data_source_id,
                'source_code' => $comparable->source_code,
                'external_reference_hash' => $comparable->external_reference_hash,
                'make' => $comparable->make,
                'model' => $comparable->model,
                'variant' => $comparable->variant,
                'year' => $comparable->year,
                'transmission' => $comparable->transmission,
                'fuel_type' => $comparable->fuel_type,
                'mileage' => $comparable->mileage,
                'listing_price' => $comparable->listing_price,
                'city' => $comparable->city,
                'observed_at' => $comparable->observed_at,
                'metadata' => [
                    ...($comparable->metadata ?? []),
                    'reused_for_automatic_retry' => true,
                ],
            ];
        }

        return $listings;
    }

    public function requestRefresh(Appraisal $appraisal, User $actor): void
    {
        DB::transaction(function () use ($appraisal, $actor): void {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());
            if (
                $locked->submitted_at === null
                || ! in_array($locked->status, [
                    AppraisalStatus::Submitted,
                    AppraisalStatus::CollectingMarketData,
                    AppraisalStatus::AutoEstimated,
                    AppraisalStatus::InsufficientComparables,
                    AppraisalStatus::UnderAppraiserReview,
                    AppraisalStatus::Failed,
                ], true)
            ) {
                throw new MarketDataProviderException(
                    'Data pasar hanya dapat diproses ulang sebelum hasil diterbitkan.',
                );
            }
            $nextStatus = AppraisalStatus::CollectingMarketData;
            $locked->update(['status' => $nextStatus]);
            $this->history(
                $locked,
                $nextStatus,
                'Data pasar diperbarui',
                'Pemrosesan otomatis OLX dan fallback OpenAI dijalankan ulang.',
                $actor,
            );

            DB::afterCommit(fn () => ProcessAppraisalMarketData::dispatch(
                $locked->getKey(),
                true,
            )->onQueue((string) config('appraisal.market_data.queue')));
        });
    }

    public function markProcessingFailed(
        Appraisal $appraisal,
        string $failureCode,
        string $failureMessage,
    ): AppraisalMarketEstimate {
        return DB::transaction(function () use (
            $appraisal,
            $failureCode,
            $failureMessage,
        ): AppraisalMarketEstimate {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());
            $version = ((int) $locked->marketEstimates()->max('version')) + 1;
            $estimate = new AppraisalMarketEstimate([
                'version' => $version,
                'status' => AppraisalMarketEstimateStatus::Failed,
                'confidence' => AppraisalConfidence::Low,
                'comparable_count' => 0,
                'failure_code' => $failureCode,
                'failure_message' => $failureMessage,
                'calculated_at' => now(),
            ]);
            $estimate->appraisal()->associate($locked);
            $estimate->save();

            if (in_array($locked->status, [
                AppraisalStatus::Submitted,
                AppraisalStatus::CollectingMarketData,
                AppraisalStatus::AutoEstimated,
                AppraisalStatus::InsufficientComparables,
                AppraisalStatus::UnderAppraiserReview,
                AppraisalStatus::Failed,
            ], true)) {
                $locked->update([
                    'status' => AppraisalStatus::Failed,
                    'assigned_appraiser_id' => null,
                ]);
                $this->history(
                    $locked,
                    AppraisalStatus::Failed,
                    'Pemrosesan otomatis belum berhasil',
                    'OLX dan fallback OpenAI belum dapat menyelesaikan appraisal. Data Anda tetap tersimpan.',
                );
            }

            return $estimate;
        });
    }

    private function history(
        Appraisal $appraisal,
        AppraisalStatus $status,
        string $title,
        string $description,
        ?User $actor = null,
    ): void {
        $history = new AppraisalStatusHistory([
            'status' => $status,
            'title' => $title,
            'description' => $description,
            'user_visible' => true,
        ]);
        $history->appraisal()->associate($appraisal);
        if ($actor !== null) {
            $history->changedBy()->associate($actor);
        }
        $history->save();
    }
}
