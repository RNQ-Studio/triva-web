<?php

namespace App\Services;

use App\Exceptions\BodyPaintConflictException;
use App\Models\BodyPaintDamagePhoto;
use App\Models\BodyPaintEstimate;
use App\Models\BodyPaintEstimateDamage;
use App\Models\BodyPaintEstimateItem;
use App\Models\BodyPaintEstimateVersion;
use App\Models\BodyPaintStatusHistory;
use App\Models\User;
use App\Support\Enums\BodyPaintAdminAction;
use App\Support\Enums\BodyPaintEstimateStatus;
use App\Support\Enums\BodyPaintPhotoReviewStatus;
use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BodyPaintEstimatorService
{
    public function __construct(
        private readonly BodyPaintEstimateService $estimates,
        private readonly BodyPaintNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(
        BodyPaintEstimate $estimate,
        User $actor,
        array $data,
    ): BodyPaintEstimate {
        $action = BodyPaintAdminAction::from($data['action']);

        return match ($action) {
            BodyPaintAdminAction::Assign => $this->assign(
                $estimate,
                $actor,
                (int) $data['estimator_id'],
            ),
            BodyPaintAdminAction::StartReview => $this->startReview(
                $estimate,
                $actor,
            ),
            BodyPaintAdminAction::RequestPhotos => $this->requestPhotos(
                $estimate,
                $actor,
                $data,
            ),
            BodyPaintAdminAction::Publish => $this->publish(
                $estimate,
                $actor,
                $data,
            ),
            BodyPaintAdminAction::ScheduleInspection => $this
                ->scheduleInspection($estimate, $actor, $data),
        };
    }

    private function assign(
        BodyPaintEstimate $estimate,
        User $actor,
        int $estimatorId,
    ): BodyPaintEstimate {
        $estimator = User::permission('bp_estimates.update')
            ->whereKey($estimatorId)
            ->firstOrFail();

        DB::transaction(function () use ($estimate, $actor, $estimator): void {
            $locked = $this->lock($estimate);
            $this->assertAction($locked, BodyPaintAdminAction::Assign);
            $locked->assignedEstimator()->associate($estimator);
            $locked->save();
            $this->history(
                $locked,
                $actor,
                'estimator_assigned',
                'Estimator ditetapkan',
                "Permintaan ditetapkan kepada {$estimator->name}.",
                false,
            );
        }, 3);

        return $this->estimates->loadAdminRelations($estimate->refresh());
    }

    private function startReview(
        BodyPaintEstimate $estimate,
        User $actor,
    ): BodyPaintEstimate {
        DB::transaction(function () use ($estimate, $actor): void {
            $locked = $this->lock($estimate);
            $this->assertAction($locked, BodyPaintAdminAction::StartReview);
            $locked->forceFill([
                'status' => BodyPaintEstimateStatus::UnderEstimatorReview,
                'assigned_estimator_id' => $locked->assigned_estimator_id
                    ?? $actor->getKey(),
                'last_status_changed_at' => now(),
            ])->save();
            $this->history(
                $locked,
                $actor,
                'estimator_review_started',
                'Review estimator dimulai',
                'Foto dan item estimasi sedang ditinjau.',
            );
            $this->notifications->record(
                $locked,
                'Estimasi sedang direview',
                "{$locked->reference_no} sedang ditinjau estimator Body & Paint.",
            );
        }, 3);

        return $this->estimates->loadAdminRelations($estimate->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestPhotos(
        BodyPaintEstimate $estimate,
        User $actor,
        array $data,
    ): BodyPaintEstimate {
        DB::transaction(function () use ($estimate, $actor, $data): void {
            $locked = $this->lock($estimate);
            $this->assertAction($locked, BodyPaintAdminAction::RequestPhotos);
            $photos = BodyPaintDamagePhoto::query()
                ->where('estimate_id', $locked->getKey())
                ->whereIn('id', $data['rejected_photo_ids'])
                ->lockForUpdate()
                ->get();
            if ($photos->count() !== count($data['rejected_photo_ids'])) {
                throw ValidationException::withMessages([
                    'rejected_photo_ids' => [
                        'Satu atau lebih foto tidak termasuk dalam estimasi ini.',
                    ],
                ]);
            }
            foreach ($photos as $photo) {
                $photo->forceFill([
                    'review_status' => BodyPaintPhotoReviewStatus::Rejected,
                    'rejection_reason_code' => $data['reason_code'],
                    'rejection_reason' => $data['reason'],
                    'reviewed_by' => $actor->getKey(),
                    'reviewed_at' => now(),
                ])->save();
            }
            $locked->forceFill([
                'status' => BodyPaintEstimateStatus::NeedsCustomerAction,
                'last_status_changed_at' => now(),
            ])->save();
            $this->history(
                $locked,
                $actor,
                'additional_photos_requested',
                'Foto tambahan diperlukan',
                $data['reason'],
                true,
                $data['reason_code'],
                ['photo_ids' => $photos->modelKeys()],
            );
            $this->notifications->record(
                $locked,
                'Foto Body & Paint perlu diperbarui',
                $data['reason'],
            );
        }, 3);

        return $this->estimates->loadAdminRelations($estimate->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function publish(
        BodyPaintEstimate $estimate,
        User $actor,
        array $data,
    ): BodyPaintEstimate {
        DB::transaction(function () use ($estimate, $actor, $data): void {
            $locked = $this->lock($estimate);
            $this->assertAction($locked, BodyPaintAdminAction::Publish);
            $locked->load(['damages', 'items']);
            $damageMap = $locked->damages->keyBy(
                fn (BodyPaintEstimateDamage $damage): string => $damage->getKey(),
            );
            $totalLow = 0;
            $totalHigh = 0;
            $durationMinHours = 0;
            $durationMaxHours = 0;
            foreach ($data['items'] as $item) {
                $totalLow += $item['labor_low']
                    + $item['material_low']
                    + $item['parts_low']
                    + $item['other_low'];
                $totalHigh += $item['labor_high']
                    + $item['material_high']
                    + $item['parts_high']
                    + $item['other_high'];
                $durationMinHours += $item['duration_min_hours'];
                $durationMaxHours += $item['duration_max_hours'];
            }
            if ($totalHigh <= 0) {
                throw ValidationException::withMessages([
                    'items' => ['Total estimasi harus lebih besar dari nol.'],
                ]);
            }
            $requiresOverride = $locked->has_high_risk_damage
                || $locked->engine_total_low === null
                || $locked->engine_total_high === null
                || $locked->engine_total_low !== $totalLow
                || $locked->engine_total_high !== $totalHigh
                || collect($data['items'])->contains(
                    function (array $item) use ($damageMap): bool {
                        $damage = $damageMap->get($item['damage_id']);

                        return $damage === null
                            || $damage->customer_severity
                                === BodyPaintSeverity::Unsure
                            || $damage->customer_severity->value
                                !== $item['severity'];
                    },
                );
            if (
                $requiresOverride
                && (
                    blank($data['override_reason_code'] ?? null)
                    || blank($data['override_reason'] ?? null)
                )
            ) {
                throw ValidationException::withMessages([
                    'override_reason' => [
                        'Alasan override wajib diisi untuk hasil manual atau hasil yang berbeda dari engine.',
                    ],
                ]);
            }

            $versionNumber = $locked->current_version + 1;
            $workdayHours = max(
                1,
                (int) config('body_paint.workday_hours', 8),
            );
            $durationMinDays = max(
                1,
                (int) ceil($durationMinHours / $workdayHours),
            );
            $durationMaxDays = max(
                $durationMinDays,
                (int) ceil($durationMaxHours / $workdayHours),
            );
            $publishedAt = now();
            $version = new BodyPaintEstimateVersion([
                'version' => $versionNumber,
                'total_low' => $totalLow,
                'total_high' => $totalHigh,
                'duration_min_days' => $durationMinDays,
                'duration_max_days' => $durationMaxDays,
                'assumptions' => $data['assumptions'],
                'disclaimer' => $data['disclaimer'],
                'override_reason_code' => $data['override_reason_code'] ?? null,
                'override_reason' => $data['override_reason'] ?? null,
                'published_by' => $actor->getKey(),
                'published_at' => $publishedAt,
            ]);
            $version->estimate()->associate($locked);
            $version->save();

            foreach ($data['items'] as $sort => $dataItem) {
                /** @var BodyPaintEstimateDamage $damage */
                $damage = $damageMap->get($dataItem['damage_id']);
                $damage->forceFill([
                    'estimator_severity' => $dataItem['severity'],
                ])->save();
                $item = new BodyPaintEstimateItem([
                    'damage_id' => $damage->getKey(),
                    'estimate_version' => $versionNumber,
                    'panel_code' => $damage->panel_code,
                    'damage_type' => $damage->damage_type,
                    'severity' => BodyPaintSeverity::from(
                        $dataItem['severity'],
                    ),
                    'work_type' => BodyPaintWorkType::from(
                        $dataItem['work_type'],
                    ),
                    'labor_low' => $dataItem['labor_low'],
                    'labor_high' => $dataItem['labor_high'],
                    'material_low' => $dataItem['material_low'],
                    'material_high' => $dataItem['material_high'],
                    'parts_low' => $dataItem['parts_low'],
                    'parts_high' => $dataItem['parts_high'],
                    'other_low' => $dataItem['other_low'],
                    'other_high' => $dataItem['other_high'],
                    'duration_min_hours' => $dataItem['duration_min_hours'],
                    'duration_max_hours' => $dataItem['duration_max_hours'],
                    'recommendation' => $dataItem['recommendation'] ?? null,
                    'is_engine_item' => false,
                    'sort_order' => $sort,
                ]);
                $item->estimate()->associate($locked);
                $item->save();
            }

            $locked->forceFill([
                'status' => BodyPaintEstimateStatus::EstimateReady,
                'published_total_low' => $totalLow,
                'published_total_high' => $totalHigh,
                'published_duration_min_days' => $durationMinDays,
                'published_duration_max_days' => $durationMaxDays,
                'current_version' => $versionNumber,
                'published_at' => $publishedAt,
                'valid_until' => $publishedAt->copy()->addDays(
                    (int) $data['valid_days'],
                ),
                'last_status_changed_at' => $publishedAt,
            ])->save();
            $this->history(
                $locked,
                $actor,
                $versionNumber === 1
                    ? 'estimate_published'
                    : 'estimate_revised',
                $versionNumber === 1
                    ? 'Estimasi tersedia'
                    : 'Estimasi direvisi',
                "Estimasi versi {$versionNumber} telah diterbitkan.",
                true,
                $data['override_reason_code'] ?? null,
                [
                    'version' => $versionNumber,
                    'total_low' => $totalLow,
                    'total_high' => $totalHigh,
                ],
            );
            $this->notifications->record(
                $locked,
                $versionNumber === 1
                    ? 'Estimasi Body & Paint tersedia'
                    : 'Estimasi Body & Paint diperbarui',
                "{$locked->reference_no} dapat dibuka untuk melihat rincian biaya.",
            );
        }, 3);

        return $this->estimates->loadAdminRelations($estimate->refresh());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function scheduleInspection(
        BodyPaintEstimate $estimate,
        User $actor,
        array $data,
    ): BodyPaintEstimate {
        DB::transaction(function () use ($estimate, $actor, $data): void {
            $locked = $this->lock($estimate);
            $this->assertAction(
                $locked,
                BodyPaintAdminAction::ScheduleInspection,
            );
            $locked->forceFill([
                'status' => BodyPaintEstimateStatus::InspectionScheduled,
                'last_status_changed_at' => now(),
            ])->save();
            $this->history(
                $locked,
                $actor,
                'inspection_scheduled',
                'Inspeksi fisik dijadwalkan',
                $data['inspection_note'] ?? null,
                true,
                metadata: ['inspection_at' => $data['inspection_at']],
            );
            $this->notifications->record(
                $locked,
                'Inspeksi Body & Paint dijadwalkan',
                'Buka detail estimasi untuk melihat jadwal inspeksi.',
            );
        }, 3);

        return $this->estimates->loadAdminRelations($estimate->refresh());
    }

    private function lock(BodyPaintEstimate $estimate): BodyPaintEstimate
    {
        return BodyPaintEstimate::query()
            ->whereKey($estimate->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertAction(
        BodyPaintEstimate $estimate,
        BodyPaintAdminAction $action,
    ): void {
        if (! in_array($action, $estimate->availableAdminActions(), true)) {
            throw new BodyPaintConflictException(
                'Aksi estimator tidak tersedia pada status saat ini.',
                'BODY_PAINT_INVALID_ADMIN_ACTION',
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    private function history(
        BodyPaintEstimate $estimate,
        User $actor,
        string $event,
        string $title,
        ?string $description,
        bool $userVisible = true,
        ?string $reasonCode = null,
        ?array $metadata = null,
    ): void {
        $history = new BodyPaintStatusHistory([
            'status' => $estimate->status,
            'event' => $event,
            'title' => $title,
            'description' => $description,
            'reason_code' => $reasonCode,
            'user_visible' => $userVisible,
            'actor_type' => 'estimator',
            'metadata' => $metadata,
        ]);
        $history->estimate()->associate($estimate);
        $history->changedBy()->associate($actor);
        $history->save();
    }
}
