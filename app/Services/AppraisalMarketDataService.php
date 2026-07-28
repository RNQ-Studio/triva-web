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
                $listings = [...$listings, ...$fetched];
                $providerCodes[] = $fallbackSource->code;
                $successfulProviderCount++;
                $fallbackAudit['status'] = 'completed';
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

        return DB::transaction(function () use (
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
                : AppraisalStatus::InsufficientComparables;
            if ($locked->status === AppraisalStatus::UnderAppraiserReview) {
                $this->history(
                    $locked,
                    AppraisalStatus::UnderAppraiserReview,
                    'Data pembanding siap ditinjau',
                    'Data pasar otomatis tersedia untuk validasi appraiser.',
                );

                return $estimate->load('comparables');
            }
            if (! in_array($locked->status, [
                AppraisalStatus::Submitted,
                AppraisalStatus::CollectingMarketData,
                AppraisalStatus::AutoEstimated,
                AppraisalStatus::InsufficientComparables,
            ], true)) {
                return $estimate->load('comparables');
            }

            $locked->update(['status' => $engineStatus]);
            $this->history(
                $locked,
                $engineStatus,
                $engineStatus === AppraisalStatus::AutoEstimated
                    ? 'Estimasi otomatis disiapkan'
                    : 'Data pembanding perlu ditinjau',
                $engineStatus === AppraisalStatus::AutoEstimated
                    ? 'Data pasar berhasil diolah dan menunggu validasi appraiser.'
                    : 'Data pembanding belum cukup. Appraiser akan melanjutkan penilaian manual.',
            );

            return $estimate->load('comparables');
        });
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
                ], true)
            ) {
                throw new MarketDataProviderException(
                    'Data pasar hanya dapat diproses ulang sebelum hasil diterbitkan.',
                );
            }
            $nextStatus = $locked->status === AppraisalStatus::UnderAppraiserReview
                ? AppraisalStatus::UnderAppraiserReview
                : AppraisalStatus::CollectingMarketData;
            $locked->update(['status' => $nextStatus]);
            $this->history(
                $locked,
                $nextStatus,
                'Data pasar diperbarui',
                'Appraiser meminta sinkronisasi ulang data pembanding.',
                $actor,
            );

            DB::afterCommit(fn () => ProcessAppraisalMarketData::dispatch(
                $locked->getKey(),
                true,
            )->onQueue((string) config('appraisal.market_data.queue')));
        });
    }

    public function markForManualReview(
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
            ], true)) {
                $locked->update(['status' => AppraisalStatus::InsufficientComparables]);
                $this->history(
                    $locked,
                    AppraisalStatus::InsufficientComparables,
                    'Penilaian dilanjutkan manual',
                    'Data pasar belum tersedia secara otomatis. Appraiser akan meninjau permintaan.',
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
