<?php

namespace App\Services;

use App\Exceptions\AppraisalConflictException;
use App\Models\Appraisal;
use App\Models\AppraisalComparable;
use App\Models\AppraisalMarketEstimate;
use App\Models\AppraisalResult;
use App\Models\AppraisalStatusHistory;
use App\Support\Enums\AppraisalMarketEstimateStatus;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Support\Facades\DB;

class AppraisalAutomaticResultPublisher
{
    public function __construct(
        private readonly PushNotificationService $notifications,
    ) {}

    public function publish(
        Appraisal $appraisal,
        AppraisalMarketEstimate $estimate,
    ): AppraisalResult {
        if ($estimate->status !== AppraisalMarketEstimateStatus::Ready) {
            throw new AppraisalConflictException(
                'Hanya rekomendasi engine yang siap yang dapat diterbitkan otomatis.',
            );
        }

        return DB::transaction(function () use ($appraisal, $estimate): AppraisalResult {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()
                ->lockForUpdate()
                ->findOrFail($appraisal->getKey());
            /** @var AppraisalMarketEstimate $lockedEstimate */
            $lockedEstimate = $locked->marketEstimates()
                ->with('comparables')
                ->findOrFail($estimate->getKey());
            $existing = $locked->results()
                ->where('market_estimate_id', $lockedEstimate->getKey())
                ->first();
            if ($existing instanceof AppraisalResult) {
                return $existing->load('comparables');
            }

            $comparables = $lockedEstimate->comparables
                ->whereNull('exclusion_reason')
                ->values();
            if ($comparables->isEmpty()) {
                throw new AppraisalConflictException(
                    'Rekomendasi engine tidak memiliki pembanding valid.',
                );
            }

            $version = ((int) $locked->results()->max('version')) + 1;
            $result = new AppraisalResult([
                'version' => $version,
                'market_estimate_id' => $lockedEstimate->getKey(),
                'market_low' => $lockedEstimate->market_low,
                'market_mid' => $lockedEstimate->market_mid,
                'market_high' => $lockedEstimate->market_high,
                'trade_in_low' => $lockedEstimate->trade_in_low,
                'trade_in_high' => $lockedEstimate->trade_in_high,
                'confidence' => $lockedEstimate->confidence,
                'comparable_count' => $comparables->count(),
                'data_as_of' => $lockedEstimate->data_as_of,
                'valid_until' => now()->addDays(
                    (int) config('appraisal.market_data.result_valid_days'),
                ),
                'requires_physical_inspection' => true,
                'disclaimer' => 'Hasil merupakan indikasi berdasarkan data listing pasar dan belum merupakan penawaran final. Nilai final memerlukan inspeksi fisik.',
                'adjustments' => $lockedEstimate->adjustments ?? [],
                'publication_type' => 'automatic_engine',
                'published_by' => null,
                'published_at' => now(),
            ]);
            $result->appraisal()->associate($locked);
            $result->save();

            foreach ($comparables as $marketComparable) {
                $comparable = new AppraisalComparable([
                    'source_code' => $marketComparable->source_code,
                    'external_reference_hash' => $marketComparable->external_reference_hash,
                    'make' => $marketComparable->make,
                    'model' => $marketComparable->model,
                    'variant' => $marketComparable->variant,
                    'year' => $marketComparable->year,
                    'mileage' => $marketComparable->mileage,
                    'listing_price' => $marketComparable->listing_price,
                    'city' => $marketComparable->city,
                    'observed_at' => $marketComparable->observed_at,
                    'similarity_score' => $marketComparable->similarity_score,
                    'is_outlier' => false,
                    'metadata' => [
                        'provenance' => 'appraisal_market_estimate',
                        'market_estimate_id' => $lockedEstimate->getKey(),
                    ],
                ]);
                $comparable->result()->associate($result);
                $comparable->save();
            }

            $locked->update([
                'status' => AppraisalStatus::ResultReady,
                'assigned_appraiser_id' => null,
            ]);
            $history = new AppraisalStatusHistory([
                'status' => AppraisalStatus::ResultReady,
                'title' => 'Hasil appraisal tersedia',
                'description' => 'OLX dan fallback OpenAI telah diproses otomatis. Hasil siap dilihat.',
                'user_visible' => true,
            ]);
            $history->appraisal()->associate($locked);
            $history->save();

            DB::afterCommit(fn () => $this->notifications->send(
                $locked->user,
                'Hasil appraisal tersedia',
                'Hasil untuk '.$locked->reference_no.' sudah dapat dibuka.',
                [
                    'appraisal_id' => $locked->getKey(),
                    'route' => '/appraisals/'.$locked->getKey(),
                ],
                'appraisal_result_ready',
            ));

            return $result->load('comparables');
        });
    }
}
