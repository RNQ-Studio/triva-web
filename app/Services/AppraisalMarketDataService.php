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
use App\Services\MarketData\OpenAiPriceDecisionProvider;
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
        private readonly OpenAiPriceDecisionProvider $aiFallback,
        private readonly AppraisalValuationEngine $engine,
        private readonly AppraisalAutomaticResultPublisher $resultPublisher,
        private readonly PushNotificationService $notifications,
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
        $retryableException = null;
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
                $source->update([
                    'last_success_at' => now(),
                    'last_error_code' => null,
                ]);
            } catch (Throwable $exception) {
                $retryableException = $exception;
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
                $decision = $this->aiFallback->decide(
                    $appraisal,
                    $fallbackSource,
                    collect($valuation['comparables'])
                        ->whereNull('exclusion_reason')
                        ->values()
                        ->all(),
                );
                $providerCodes[] = $fallbackSource->code;
                $fallbackAudit['status'] = 'completed';
                $fallbackSource->update([
                    'last_success_at' => now(),
                    'last_error_code' => null,
                ]);
                $valuation = $this->engine->estimateFromPriceDecision(
                    $appraisal,
                    $decision,
                    $valuation,
                );
            } catch (Throwable $exception) {
                $fallbackAudit['status'] = 'failed';
                $fallbackAudit['error_code'] = $exception instanceof AiAgentException
                    ? $exception->errorCode
                    : 'ai_fallback_failed';
                $fallbackSource->update([
                    'last_failure_at' => now(),
                    'last_error_code' => $fallbackAudit['error_code'],
                ]);
                if ($this->isRetryable($exception)) {
                    $retryableException = $exception;
                }
            }
        }

        if (
            $valuation['status'] !== AppraisalMarketEstimateStatus::Ready
            && $retryableException !== null
        ) {
            throw new MarketDataProviderException(
                'Provider appraisal otomatis gagal sementara.',
                previous: $retryableException,
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
        } else {
            $this->notifyFailure($appraisal->refresh());
        }

        return $estimate;
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
        $estimate = DB::transaction(function () use (
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

        $this->notifyFailure($appraisal->refresh());

        return $estimate;
    }

    private function isRetryable(Throwable $exception): bool
    {
        if (! $exception instanceof AiAgentException) {
            return true;
        }

        return in_array($exception->errorCode, [
            'openai_connection_failed',
            'openai_rate_limited',
            'openai_server_error',
            'openai_incomplete_response',
            'ai_rate_limited',
        ], true);
    }

    private function notifyFailure(Appraisal $appraisal): void
    {
        $this->notifications->send(
            $appraisal->user,
            'Appraisal belum dapat diselesaikan',
            'Pemrosesan '.$appraisal->reference_no.' selesai, tetapi keputusan harga belum tersedia. Data Anda tetap tersimpan.',
            [
                'appraisal_id' => $appraisal->getKey(),
                'route' => '/appraisals/'.$appraisal->getKey(),
            ],
            'appraisal_processing_failed',
        );
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
