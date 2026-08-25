<?php

namespace App\Services;

use App\Exceptions\AppraisalConflictException;
use App\Jobs\ProcessAppraisalMarketData;
use App\Models\Appraisal;
use App\Models\AppraisalPhoto;
use App\Models\AppraisalStatusHistory;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Enums\AppraisalDecision;
use App\Support\Enums\AppraisalPhotoAngle;
use App\Support\Enums\AppraisalPhotoReviewStatus;
use App\Support\Enums\AppraisalStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppraisalService
{
    /** @return array{appraisal: Appraisal, replayed: bool} */
    public function createDraft(
        User $user,
        Vehicle $vehicle,
        ?string $idempotencyKey = null,
    ): array {
        if ($vehicle->user_id !== $user->getKey()) {
            throw new AppraisalConflictException('Kendaraan tidak dapat digunakan untuk appraisal ini.');
        }

        if ($idempotencyKey === null) {
            return [
                'appraisal' => DB::transaction(
                    fn (): Appraisal => $this->persistDraft($user, $vehicle),
                ),
                'replayed' => false,
            ];
        }

        $fingerprint = hash('sha256', (string) $vehicle->getKey());
        $existing = $this->findDraftByCreationKey($user, $idempotencyKey);
        if ($existing !== null) {
            return $this->replayCreatedDraft($existing, $fingerprint);
        }

        try {
            return DB::transaction(function () use (
                $user,
                $vehicle,
                $idempotencyKey,
                $fingerprint,
            ): array {
                $existing = $this->findDraftByCreationKey(
                    $user,
                    $idempotencyKey,
                    true,
                );
                if ($existing !== null) {
                    return $this->replayCreatedDraft($existing, $fingerprint);
                }

                return [
                    'appraisal' => $this->persistDraft(
                        $user,
                        $vehicle,
                        $idempotencyKey,
                        $fingerprint,
                    ),
                    'replayed' => false,
                ];
            }, 3);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23505') {
                throw $exception;
            }

            $existing = $this->findDraftByCreationKey($user, $idempotencyKey);
            if ($existing !== null) {
                return $this->replayCreatedDraft($existing, $fingerprint);
            }

            throw $exception;
        }
    }

    private function persistDraft(
        User $user,
        Vehicle $vehicle,
        ?string $idempotencyKey = null,
        ?string $fingerprint = null,
    ): Appraisal {
        $appraisal = new Appraisal([
            'reference_no' => $this->referenceNumber(),
            'status' => AppraisalStatus::Draft,
            'creation_idempotency_key' => $idempotencyKey,
            'creation_idempotency_fingerprint' => $fingerprint,
        ]);
        $appraisal->user()->associate($user);
        $appraisal->vehicle()->associate($vehicle);
        $appraisal->save();

        $this->history(
            $appraisal,
            AppraisalStatus::Draft,
            'Draft appraisal dibuat',
            'Data Anda tersimpan dan dapat dilanjutkan.',
            $user,
        );

        return $this->loadCustomerRelations($appraisal);
    }

    private function findDraftByCreationKey(
        User $user,
        string $idempotencyKey,
        bool $lock = false,
    ): ?Appraisal {
        $query = Appraisal::query()
            ->where('user_id', $user->getKey())
            ->where('creation_idempotency_key', $idempotencyKey);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @return array{appraisal: Appraisal, replayed: true} */
    private function replayCreatedDraft(
        Appraisal $appraisal,
        string $fingerprint,
    ): array {
        if (! hash_equals(
            (string) $appraisal->creation_idempotency_fingerprint,
            $fingerprint,
        )) {
            throw new AppraisalConflictException(
                'Idempotency-Key sudah digunakan untuk kendaraan appraisal yang berbeda.',
                'APPRAISAL_IDEMPOTENCY_CONFLICT',
            );
        }

        return [
            'appraisal' => $this->loadCustomerRelations($appraisal),
            'replayed' => true,
        ];
    }

    /** @param array<string, int|string> $condition */
    public function updateCondition(Appraisal $appraisal, array $condition, User $user): Appraisal
    {
        $appraisal->update($condition);

        return $this->loadCustomerRelations($appraisal);
    }

    /** @param list<array{angle: string, asset_id: string}> $photos */
    public function attachPhotos(Appraisal $appraisal, array $photos, User $user): Appraisal
    {
        DB::transaction(function () use ($appraisal, $photos): void {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());

            if (! $locked->status->isCustomerEditable()) {
                throw new AppraisalConflictException('Foto tidak dapat diubah pada status appraisal saat ini.');
            }

            foreach ($photos as $payload) {
                $angle = AppraisalPhotoAngle::from($payload['angle']);
                $current = AppraisalPhoto::query()
                    ->where('appraisal_id', $locked->getKey())
                    ->where('angle', $angle)
                    ->where('is_current', true)
                    ->lockForUpdate()
                    ->first();

                if ($current?->asset_id === $payload['asset_id']) {
                    continue;
                }

                if (
                    $locked->status === AppraisalStatus::NeedsCustomerAction
                    && $current?->review_status !== AppraisalPhotoReviewStatus::Rejected
                ) {
                    throw new AppraisalConflictException('Hanya foto yang ditolak yang dapat diganti.');
                }

                $nextVersion = $current === null ? 1 : $current->version + 1;
                if ($current !== null) {
                    $current->update(['is_current' => false]);
                }

                $photo = new AppraisalPhoto([
                    'asset_id' => $payload['asset_id'],
                    'angle' => $angle,
                    'version' => $nextVersion,
                    'is_current' => true,
                    'review_status' => AppraisalPhotoReviewStatus::Pending,
                ]);
                $photo->appraisal()->associate($locked);
                $photo->save();
            }
        });

        return $this->loadCustomerRelations($appraisal->refresh());
    }

    public function submit(
        Appraisal $appraisal,
        User $user,
        string $idempotencyKey,
        bool $marketingConsent,
    ): Appraisal {
        DB::transaction(function () use ($appraisal, $user, $idempotencyKey, $marketingConsent): void {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());

            if ($locked->submitted_at !== null) {
                if ($locked->idempotency_key === $idempotencyKey) {
                    return;
                }

                throw new AppraisalConflictException('Appraisal ini sudah pernah dikirim.');
            }

            if ($locked->status !== AppraisalStatus::Draft) {
                throw new AppraisalConflictException('Draft appraisal tidak dapat dikirim pada status saat ini.');
            }

            if (! filled($user->phone) || ! filled($user->city) || $user->service_consent_at === null) {
                throw new AppraisalConflictException('Lengkapi profil dan persetujuan layanan sebelum mengirim appraisal.');
            }

            if (! $locked->conditionIsComplete()) {
                throw new AppraisalConflictException('Lengkapi seluruh checklist kondisi kendaraan.');
            }

            $angles = AppraisalPhoto::query()
                ->where('appraisal_id', $locked->getKey())
                ->where('is_current', true)
                ->get(['angle'])
                ->map(fn (AppraisalPhoto $photo): string => $photo->angle->value)
                ->sort()
                ->values()
                ->all();

            $requiredAngles = collect(AppraisalPhotoAngle::cases())
                ->map(fn (AppraisalPhotoAngle $angle): string => $angle->value)
                ->sort()
                ->values()
                ->all();

            if ($angles !== $requiredAngles) {
                throw new AppraisalConflictException('Lengkapi tepat lima sudut foto wajib sebelum mengirim.');
            }

            $submittedAt = now();
            $locked->update([
                'status' => AppraisalStatus::CollectingMarketData,
                'idempotency_key' => $idempotencyKey,
                'service_consent_at' => $user->service_consent_at,
                'marketing_consent' => $marketingConsent,
                'submitted_at' => $submittedAt,
                'due_at' => $submittedAt->copy()->addMinutes(15),
            ]);

            $this->history(
                $locked,
                AppraisalStatus::Submitted,
                'Permintaan diterima',
                'Data kendaraan dan lima foto berhasil dikirim.',
                $user,
            );
            $this->history(
                $locked,
                AppraisalStatus::CollectingMarketData,
                'Mencari data pembanding',
                'Sistem sedang mengambil dan mengolah data pasar yang disetujui.',
            );

            DB::afterCommit(fn () => ProcessAppraisalMarketData::dispatch(
                $locked->getKey(),
            )->onQueue((string) config('appraisal.market_data.queue')));
        });

        return $this->loadCustomerRelations($appraisal->refresh());
    }

    public function resubmit(Appraisal $appraisal, User $user): Appraisal
    {
        DB::transaction(function () use ($appraisal, $user): void {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());

            if ($locked->status !== AppraisalStatus::NeedsCustomerAction) {
                throw new AppraisalConflictException('Appraisal ini tidak sedang menunggu perbaikan.');
            }

            $hasRejectedPhoto = AppraisalPhoto::query()
                ->where('appraisal_id', $locked->getKey())
                ->where('is_current', true)
                ->where('review_status', AppraisalPhotoReviewStatus::Rejected)
                ->exists();

            if ($hasRejectedPhoto) {
                throw new AppraisalConflictException('Ganti seluruh foto yang ditolak sebelum mengirim ulang.');
            }

            $locked->update([
                'status' => AppraisalStatus::CollectingMarketData,
                'assigned_appraiser_id' => null,
            ]);
            $this->history(
                $locked,
                AppraisalStatus::CollectingMarketData,
                'Perbaikan diterima',
                'Foto pengganti diterima dan pemrosesan otomatis dijalankan ulang.',
                $user,
            );

            DB::afterCommit(fn () => ProcessAppraisalMarketData::dispatch(
                $locked->getKey(),
                true,
            )->onQueue((string) config('appraisal.market_data.queue')));
        });

        return $this->loadCustomerRelations($appraisal->refresh());
    }

    public function decide(
        Appraisal $appraisal,
        User $user,
        AppraisalDecision $decision,
        ?int $expectedPrice = null,
    ): Appraisal {
        DB::transaction(function () use ($appraisal, $user, $decision, $expectedPrice): void {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());

            if ($locked->status !== AppraisalStatus::ResultReady) {
                throw new AppraisalConflictException('Keputusan hanya dapat disimpan saat hasil tersedia.');
            }

            $result = $locked->latestResult()->first();
            if ($result === null || $result->valid_until->isPast()) {
                $locked->update(['status' => AppraisalStatus::Expired]);
                throw new AppraisalConflictException('Hasil appraisal sudah kedaluwarsa. Minta penilaian ulang.');
            }

            $status = match ($decision) {
                AppraisalDecision::Accepted => AppraisalStatus::AcceptedByCustomer,
                AppraisalDecision::Rejected => AppraisalStatus::RejectedByCustomer,
                AppraisalDecision::Deferred => AppraisalStatus::ResultReady,
            };

            $locked->update([
                'status' => $status,
                'customer_decision' => $decision,
                'customer_decided_at' => now(),
                // Harapan harga hanya disimpan bersama penolakan; keputusan lain
                // tidak boleh menghapus angka yang pernah dikirim pelanggan.
                ...$expectedPrice === null ? [] : [
                    'expected_price' => $expectedPrice,
                    'expected_price_submitted_at' => now(),
                ],
            ]);

            $this->history(
                $locked,
                $status,
                match ($decision) {
                    AppraisalDecision::Accepted => 'Harga diterima',
                    AppraisalDecision::Rejected => 'Harga belum cocok',
                    AppraisalDecision::Deferred => 'Keputusan ditunda',
                },
                match ($decision) {
                    AppraisalDecision::Accepted => 'Nilai appraisal siap digunakan sebagai opsi DP.',
                    AppraisalDecision::Rejected => $expectedPrice === null
                        ? 'Kendaraan siap dilanjutkan ke Estimasi Perbaikan BP.'
                        : 'Harapan harga pelanggan Rp '
                            .number_format($expectedPrice, 0, ',', '.')
                            .' tercatat untuk ditindaklanjuti.',
                    AppraisalDecision::Deferred => 'Hasil tetap tersedia di Aktivitas Saya.',
                },
                $user,
            );
        });

        return $this->loadCustomerRelations($appraisal->refresh());
    }

    public function scheduleInspection(
        Appraisal $appraisal,
        User $user,
        string $scheduledAt,
        ?string $notes,
    ): Appraisal {
        DB::transaction(function () use ($appraisal, $user, $scheduledAt, $notes): void {
            /** @var Appraisal $locked */
            $locked = Appraisal::query()->lockForUpdate()->findOrFail($appraisal->getKey());
            if ($locked->status !== AppraisalStatus::ResultReady) {
                throw new AppraisalConflictException('Inspeksi hanya dapat dijadwalkan saat hasil tersedia.');
            }

            $locked->update([
                'status' => AppraisalStatus::InspectionScheduled,
                'inspection_scheduled_at' => $scheduledAt,
                'inspection_notes' => $notes,
            ]);
            $this->history(
                $locked,
                AppraisalStatus::InspectionScheduled,
                'Inspeksi dijadwalkan',
                'Tim TRIVA akan menindaklanjuti jadwal inspeksi kendaraan.',
                $user,
            );
        });

        return $this->loadCustomerRelations($appraisal->refresh());
    }

    public function loadCustomerRelations(Appraisal $appraisal): Appraisal
    {
        return $appraisal->load([
            'vehicle',
            'currentPhotos.asset',
            'statusHistories' => fn ($query) => $query
                ->where('user_visible', true)
                ->oldest('created_at'),
            'latestResult.comparables',
            'latestResult.marketEstimate',
        ]);
    }

    private function referenceNumber(): string
    {
        do {
            $uuid = str_replace('-', '', (string) Str::uuid());
            $number = str_pad((string) (hexdec(substr($uuid, 0, 10)) % 100000000), 8, '0', STR_PAD_LEFT);
            $reference = 'TIA-'.now()->format('Ymd').'-'.$number;
        } while (Appraisal::query()->where('reference_no', $reference)->exists());

        return $reference;
    }

    private function history(
        Appraisal $appraisal,
        AppraisalStatus $status,
        string $title,
        ?string $description = null,
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
