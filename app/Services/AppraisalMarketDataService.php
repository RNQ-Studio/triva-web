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
use App\Models\CreditProgram;
use App\Models\MarketDataSource;
use App\Models\User;
use App\Services\MarketData\OlxApprovedHtmlProvider;
use App\Services\MarketData\OpenAiPriceDecisionProvider;
use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalMarketEstimateStatus;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        private readonly AppraisalValuationFingerprint $fingerprints,
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

        $fingerprint = $this->fingerprints->for($appraisal);
        $reusable = $this->reusableEstimate($appraisal, $fingerprint);
        if ($reusable instanceof AppraisalMarketEstimate) {
            return $this->persist(
                $appraisal,
                $this->valuationFrom($reusable),
                $reusable->provider_codes ?? [],
                $fingerprint,
            );
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

        $depreciationAudit = ['attempted' => false, 'status' => 'not_needed'];
        // Depresiasi hanya menutup kasus "belum pernah ada yang menjual", bukan
        // gangguan sementara provider. Kegagalan yang masih bisa diulang tetap
        // dilempar supaya antrean mencoba lagi dengan data pasar sungguhan.
        if (
            $valuation['status'] !== AppraisalMarketEstimateStatus::Ready
            && $retryableException === null
        ) {
            $newVehiclePrice = $this->newVehiclePrice($appraisal);
            $depreciationAudit['attempted'] = true;
            if (
                ! (bool) config('appraisal.depreciation.enabled')
            ) {
                $depreciationAudit['status'] = 'disabled';
            } elseif ($newVehiclePrice === null) {
                // Tanpa harga unit baru tidak ada dasar depresiasi. Mengarang
                // angka di sini lebih buruk daripada mengatakan data belum
                // memadai.
                $depreciationAudit['status'] = 'no_new_vehicle_price';
            } else {
                $depreciationAudit['status'] = 'completed';
                $valuation = $this->engine->estimateFromDepreciation(
                    $appraisal,
                    $newVehiclePrice,
                    $valuation,
                );
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
        $valuation['calculation']['depreciation_fallback'] = $depreciationAudit;

        return $this->persist($appraisal, $valuation, $providerCodes, $fingerprint);
    }

    /**
     * Menyimpan hasil penilaian sebagai estimasi baru lalu menerbitkan atau
     * memberitahukan kegagalannya. Dipakai jalur perhitungan penuh maupun
     * jalur pemakaian ulang hasil dengan sidik jari yang sama.
     *
     * @param  array<string, mixed>  $valuation
     * @param  list<string>  $providerCodes
     */
    private function persist(
        Appraisal $appraisal,
        array $valuation,
        array $providerCodes,
        string $fingerprint,
    ): AppraisalMarketEstimate {
        $estimate = DB::transaction(function () use (
            $appraisal,
            $valuation,
            $providerCodes,
            $fingerprint,
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
                'valuation_fingerprint' => $fingerprint,
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

    /**
     * Harga unit baru yang sepadan, diambil dari katalog kredit yang berlaku.
     * Kecocokan varian didahulukan; bila tidak ada, model saja sudah cukup
     * sebagai dasar depresiasi.
     */
    private function newVehiclePrice(Appraisal $appraisal): ?int
    {
        $vehicle = $appraisal->vehicle;
        if ($vehicle === null) {
            return null;
        }

        $programs = CreditProgram::query()
            ->effective()
            ->where('vehicle_model', $vehicle->model)
            ->get(['vehicle_variant', 'otr_price']);
        if ($programs->isEmpty()) {
            return null;
        }

        $variantMatch = $programs->first(
            fn (CreditProgram $program): bool => $this->normalizeText($program->vehicle_variant)
                === $this->normalizeText($vehicle->variant),
        );

        return (int) ($variantMatch?->otr_price ?? $programs->min('otr_price'));
    }

    private function normalizeText(string $value): string
    {
        return (string) Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '');
    }

    /**
     * Mencari hasil penilaian yang sudah siap untuk kendaraan dan kondisi yang
     * persis sama, milik appraisal mana pun, selama data pasarnya belum
     * kedaluwarsa. Inilah yang membuat dua akun dengan isian identik menerima
     * angka identik.
     */
    private function reusableEstimate(
        Appraisal $appraisal,
        string $fingerprint,
    ): ?AppraisalMarketEstimate {
        return AppraisalMarketEstimate::query()
            ->where('valuation_fingerprint', $fingerprint)
            ->where('status', AppraisalMarketEstimateStatus::Ready)
            ->where('appraisal_id', '!=', $appraisal->getKey())
            ->where('calculated_at', '>=', now()->subDays(
                (int) config('appraisal.market_data.result_valid_days'),
            ))
            ->with('comparables')
            ->latest('calculated_at')
            ->first();
    }

    /**
     * Menyalin angka estimasi yang dipakai ulang ke bentuk kontrak valuation,
     * termasuk pembandingnya, karena penerbitan hasil menolak estimasi tanpa
     * pembanding valid.
     *
     * @return array<string, mixed>
     */
    private function valuationFrom(AppraisalMarketEstimate $estimate): array
    {
        $calculation = $estimate->calculation ?? [];
        $calculation['reused_from'] = [
            'market_estimate_id' => $estimate->getKey(),
            'appraisal_id' => $estimate->appraisal_id,
            'calculated_at' => $estimate->calculated_at->toIso8601String(),
            'reason' => 'identical_vehicle_and_condition_fingerprint',
        ];

        return [
            'status' => $estimate->status,
            'market_low' => $estimate->market_low,
            'market_mid' => $estimate->market_mid,
            'market_high' => $estimate->market_high,
            'trade_in_low' => $estimate->trade_in_low,
            'trade_in_high' => $estimate->trade_in_high,
            'confidence' => $estimate->confidence,
            'comparable_count' => $estimate->comparable_count,
            'data_as_of' => $estimate->data_as_of,
            'adjustments' => $estimate->adjustments ?? [],
            'calculation' => $calculation,
            'comparables' => $estimate->comparables
                ->map(fn (AppraisalMarketComparable $comparable): array => [
                    'market_data_source_id' => $comparable->market_data_source_id,
                    'source_code' => $comparable->source_code,
                    'external_reference_hash' => $comparable->external_reference_hash,
                    'deduplication_hash' => $comparable->deduplication_hash,
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
                    'similarity_score' => $comparable->similarity_score,
                    'weight' => $comparable->weight,
                    'is_duplicate' => $comparable->is_duplicate,
                    'is_outlier' => $comparable->is_outlier,
                    'exclusion_reason' => $comparable->exclusion_reason,
                    'metadata' => $comparable->metadata,
                ])
                ->values()
                ->all(),
        ];
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
