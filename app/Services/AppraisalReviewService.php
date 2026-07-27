<?php

namespace App\Services;

use App\Exceptions\AppraisalConflictException;
use App\Models\Appraisal;
use App\Models\AppraisalComparable;
use App\Models\AppraisalMarketEstimate;
use App\Models\AppraisalPhoto;
use App\Models\AppraisalResult;
use App\Models\AppraisalStatusHistory;
use App\Models\User;
use App\Support\Enums\AppraisalConfidence;
use App\Support\Enums\AppraisalPhotoAngle;
use App\Support\Enums\AppraisalPhotoReviewStatus;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Support\Facades\DB;

class AppraisalReviewService
{
    public function __construct(
        private readonly PushNotificationService $notifications,
    ) {}

    public function startReview(Appraisal $appraisal, User $appraiser): Appraisal
    {
        DB::transaction(function () use ($appraisal, $appraiser): void {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());
            if (! in_array($locked->status, [
                AppraisalStatus::Submitted,
                AppraisalStatus::CollectingMarketData,
                AppraisalStatus::AutoEstimated,
                AppraisalStatus::InsufficientComparables,
                AppraisalStatus::UnderAppraiserReview,
            ], true)) {
                throw new AppraisalConflictException('Appraisal tidak dapat dimulai review pada status ini.');
            }

            $locked->update([
                'status' => AppraisalStatus::UnderAppraiserReview,
                'assigned_appraiser_id' => $appraiser->getKey(),
            ]);
            $this->history(
                $locked,
                AppraisalStatus::UnderAppraiserReview,
                'Validasi appraiser',
                'Appraiser sedang memvalidasi data kendaraan, foto, dan pembanding.',
                $appraiser,
            );
        });

        return $appraisal->refresh();
    }

    public function requestPhotoCorrection(
        Appraisal $appraisal,
        User $appraiser,
        AppraisalPhotoAngle $angle,
        string $note,
    ): Appraisal {
        DB::transaction(function () use ($appraisal, $appraiser, $angle, $note): void {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());
            if (! in_array($locked->status, [
                AppraisalStatus::CollectingMarketData,
                AppraisalStatus::AutoEstimated,
                AppraisalStatus::InsufficientComparables,
                AppraisalStatus::UnderAppraiserReview,
            ], true)) {
                throw new AppraisalConflictException('Perbaikan foto tidak dapat diminta pada status ini.');
            }

            $photo = AppraisalPhoto::query()
                ->where('appraisal_id', $locked->getKey())
                ->where('angle', $angle)
                ->where('is_current', true)
                ->lockForUpdate()
                ->firstOrFail();

            $photo->update([
                'review_status' => AppraisalPhotoReviewStatus::Rejected,
                'rejection_note' => $note,
                'reviewed_by' => $appraiser->getKey(),
                'reviewed_at' => now(),
            ]);
            $locked->update([
                'status' => AppraisalStatus::NeedsCustomerAction,
                'assigned_appraiser_id' => $appraiser->getKey(),
            ]);
            $this->history(
                $locked,
                AppraisalStatus::NeedsCustomerAction,
                'Foto '.$angle->label().' perlu diulang',
                $note,
                $appraiser,
            );

            DB::afterCommit(fn () => $this->notifications->send(
                $locked->user,
                'Foto appraisal perlu diperbaiki',
                'Buka appraisal '.$locked->reference_no.' untuk mengganti foto '.$angle->label().'.',
                ['appraisal_id' => $locked->getKey(), 'route' => '/appraisals/'.$locked->getKey()],
                'appraisal_needs_action',
            ));
        });

        return $appraisal->refresh();
    }

    /**
     * @param  array<string, mixed>  $resultData
     * @param  list<array<string, mixed>>  $comparables
     */
    public function publishResult(
        Appraisal $appraisal,
        User $appraiser,
        array $resultData,
        array $comparables,
    ): AppraisalResult {
        if ($comparables === []) {
            throw new AppraisalConflictException('Minimal satu pembanding dengan provenance wajib disimpan.');
        }

        if (
            $resultData['market_low'] > $resultData['market_mid']
            || $resultData['market_mid'] > $resultData['market_high']
            || $resultData['trade_in_low'] > $resultData['trade_in_high']
        ) {
            throw new AppraisalConflictException('Urutan rentang harga tidak valid.');
        }

        return DB::transaction(function () use ($appraisal, $appraiser, $resultData, $comparables): AppraisalResult {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());
            if (! in_array($locked->status, [
                AppraisalStatus::CollectingMarketData,
                AppraisalStatus::AutoEstimated,
                AppraisalStatus::InsufficientComparables,
                AppraisalStatus::UnderAppraiserReview,
                AppraisalStatus::ResultReady,
            ], true)) {
                throw new AppraisalConflictException('Hasil tidak dapat diterbitkan pada status ini.');
            }

            $marketEstimate = $this->marketEstimate($locked, $resultData['market_estimate_id'] ?? null);
            $isOverride = $marketEstimate === null
                || $this->pricesDifferFromEstimate($marketEstimate, $resultData)
                || $this->comparablesDifferFromEstimate($marketEstimate, $comparables);
            $overrideReasonCode = $resultData['override_reason_code'] ?? null;
            $overrideNotes = $resultData['override_notes'] ?? null;
            if ($isOverride && (blank($overrideReasonCode) || mb_strlen(trim((string) $overrideNotes)) < 20)) {
                throw new AppraisalConflictException(
                    'Override/manual appraisal wajib memiliki reason code dan catatan minimal 20 karakter.',
                );
            }

            $validComparableCount = collect($comparables)
                ->where('is_outlier', false)
                ->count();
            $version = ((int) $locked->results()->max('version')) + 1;
            $result = new AppraisalResult([
                ...$resultData,
                'version' => $version,
                'market_estimate_id' => $marketEstimate?->getKey(),
                'publication_type' => match (true) {
                    $marketEstimate === null => 'manual',
                    $isOverride => 'appraiser_override',
                    default => 'approved_engine',
                },
                'override_reason_code' => $isOverride ? $overrideReasonCode : null,
                'override_notes' => $isOverride ? trim((string) $overrideNotes) : null,
                'confidence' => AppraisalConfidence::fromComparableCount($validComparableCount),
                'comparable_count' => $validComparableCount,
                'published_by' => $appraiser->getKey(),
                'published_at' => now(),
            ]);
            $result->appraisal()->associate($locked);
            $result->save();

            foreach ($comparables as $payload) {
                $comparable = new AppraisalComparable($payload);
                $comparable->result()->associate($result);
                $comparable->save();
            }

            $locked->update([
                'status' => AppraisalStatus::ResultReady,
                'assigned_appraiser_id' => $appraiser->getKey(),
            ]);
            $this->history(
                $locked,
                AppraisalStatus::ResultReady,
                'Hasil appraisal tersedia',
                'Rentang indikasi telah divalidasi appraiser dan siap dilihat.',
                $appraiser,
            );

            DB::afterCommit(fn () => $this->notifications->send(
                $locked->user,
                'Hasil appraisal tersedia',
                'Hasil untuk '.$locked->reference_no.' sudah dapat dibuka.',
                ['appraisal_id' => $locked->getKey(), 'route' => '/appraisals/'.$locked->getKey()],
                'appraisal_result_ready',
            ));

            return $result->load('comparables');
        });
    }

    private function marketEstimate(
        Appraisal $appraisal,
        mixed $marketEstimateId,
    ): ?AppraisalMarketEstimate {
        if (blank($marketEstimateId)) {
            return null;
        }

        $estimate = $appraisal->marketEstimates()->find((string) $marketEstimateId);
        if ($estimate === null) {
            throw new AppraisalConflictException('Rekomendasi engine tidak berasal dari appraisal ini.');
        }

        return $estimate;
    }

    /** @param array<string, mixed> $resultData */
    private function pricesDifferFromEstimate(
        AppraisalMarketEstimate $estimate,
        array $resultData,
    ): bool {
        foreach ([
            'market_low',
            'market_mid',
            'market_high',
            'trade_in_low',
            'trade_in_high',
        ] as $field) {
            if ((int) $resultData[$field] !== (int) $estimate->{$field}) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $comparables */
    private function comparablesDifferFromEstimate(
        AppraisalMarketEstimate $estimate,
        array $comparables,
    ): bool {
        $engineFingerprints = $estimate->comparables()
            ->whereNull('exclusion_reason')
            ->get()
            ->map(fn ($comparable): string => $this->comparableFingerprint([
                'source_code' => $comparable->source_code,
                'external_reference_hash' => $comparable->external_reference_hash,
                'make' => $comparable->make,
                'model' => $comparable->model,
                'variant' => $comparable->variant,
                'year' => $comparable->year,
                'mileage' => $comparable->mileage,
                'listing_price' => $comparable->listing_price,
                'city' => $comparable->city,
            ]))
            ->sort()
            ->values()
            ->all();
        $publishedFingerprints = collect($comparables)
            ->where('is_outlier', false)
            ->map(fn (array $comparable): string => $this->comparableFingerprint($comparable))
            ->sort()
            ->values()
            ->all();

        return $engineFingerprints !== $publishedFingerprints;
    }

    /** @param array<string, mixed> $comparable */
    private function comparableFingerprint(array $comparable): string
    {
        if (filled($comparable['external_reference_hash'] ?? null)) {
            return (string) $comparable['external_reference_hash'];
        }

        return hash('sha256', implode('|', [
            (string) ($comparable['source_code'] ?? ''),
            (string) ($comparable['make'] ?? ''),
            (string) ($comparable['model'] ?? ''),
            (string) ($comparable['variant'] ?? ''),
            (string) ($comparable['year'] ?? ''),
            (string) ($comparable['mileage'] ?? ''),
            (string) ($comparable['listing_price'] ?? ''),
            (string) ($comparable['city'] ?? ''),
        ]));
    }

    private function history(
        Appraisal $appraisal,
        AppraisalStatus $status,
        string $title,
        string $description,
        User $actor,
    ): void {
        $history = new AppraisalStatusHistory([
            'status' => $status,
            'title' => $title,
            'description' => $description,
            'user_visible' => true,
            'changed_by' => $actor->getKey(),
        ]);
        $history->appraisal()->associate($appraisal);
        $history->save();
    }
}
