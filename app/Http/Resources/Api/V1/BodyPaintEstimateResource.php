<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BodyPaintDamagePhoto;
use App\Models\BodyPaintEstimate;
use App\Models\BodyPaintEstimateDamage;
use App\Models\BodyPaintEstimateItem;
use App\Models\BodyPaintStatusHistory;
use App\Support\BodyPaintCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BodyPaintEstimate */
class BodyPaintEstimateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $published = $this->status->exposesPublishedEstimate()
            ? $this->publishedResult()
            : null;

        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'allowed_customer_actions' => $this->status->customerActions(),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'source_appraisal_id' => $this->appraisal_id,
            'service_location' => new ToyotaServiceLocationResource(
                $this->whenLoaded('serviceLocation'),
            ),
            'customer_notes' => $this->customer_notes,
            'damages' => $this->relationLoaded('damages')
                ? $this->damages->map(
                    fn (BodyPaintEstimateDamage $damage): array => [
                        'id' => $damage->getKey(),
                        'panel_code' => $damage->panel_code,
                        'panel_label' => BodyPaintCatalog::PANELS[
                            $damage->panel_code
                        ] ?? $damage->panel_code,
                        'damage_type' => $damage->damage_type,
                        'damage_type_label' => BodyPaintCatalog::DAMAGE_TYPES[
                            $damage->damage_type
                        ] ?? $damage->damage_type,
                        'customer_severity' => $damage
                            ->customer_severity
                            ->value,
                        'customer_severity_label' => $damage
                            ->customer_severity
                            ->label(),
                        'estimator_severity' => $damage
                            ->estimator_severity
                            ?->value,
                        'customer_note' => $damage->customer_note,
                        'estimator_note' => $damage->estimator_note,
                        'is_high_risk' => $damage->is_high_risk,
                        'photos' => $damage->relationLoaded('photos')
                            ? $damage->photos->map(
                                fn (BodyPaintDamagePhoto $photo): array => $this
                                    ->photo($photo),
                            )->values()
                            : [],
                    ],
                )->values()->all()
                : [],
            'context_photos' => $this->relationLoaded('photos')
                ? $this->photos
                    ->whereNull('damage_id')
                    ->map(
                        fn (BodyPaintDamagePhoto $photo): array => $this
                            ->photo($photo),
                    )
                    ->values()
                    ->all()
                : [],
            'estimate' => $published,
            'requires_physical_inspection' => $this
                ->requires_physical_inspection,
            'booking' => $this->whenLoaded('booking', function (): ?array {
                if ($this->booking === null) {
                    return null;
                }

                return [
                    'id' => $this->booking->getKey(),
                    'reference_no' => $this->booking->reference_no,
                    'status' => $this->booking->status->value,
                    'status_label' => $this->booking->status->customerLabel(),
                    'route' => '/toyota-service/bookings/'
                        .$this->booking->getKey(),
                ];
            }),
            'timeline' => $this->relationLoaded('statusHistories')
                ? $this->statusHistories->map(
                    fn (BodyPaintStatusHistory $history): array => [
                        'id' => $history->getKey(),
                        'status' => $history->status->value,
                        'event' => $history->event,
                        'title' => $history->title,
                        'description' => $history->description,
                        'reason_code' => $history->reason_code,
                        'metadata' => $history->metadata,
                        'created_at' => $history->created_at
                            ?->toIso8601String(),
                    ],
                )->values()->all()
                : [],
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'due_at' => $this->due_at?->toIso8601String(),
            'published_at' => $this->published_at?->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'declined_at' => $this->declined_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function publishedResult(): ?array
    {
        $version = $this->currentPublishedVersion;
        if ($version === null) {
            return null;
        }
        $items = $this->relationLoaded('items')
            ? $this->items
                ->where('estimate_version', $version->version)
                ->map(fn (BodyPaintEstimateItem $item): array => [
                    'id' => $item->getKey(),
                    'damage_id' => $item->damage_id,
                    'panel_code' => $item->panel_code,
                    'panel_label' => BodyPaintCatalog::PANELS[
                        $item->panel_code
                    ] ?? $item->panel_code,
                    'damage_type' => $item->damage_type,
                    'damage_type_label' => BodyPaintCatalog::DAMAGE_TYPES[
                        $item->damage_type
                    ] ?? $item->damage_type,
                    'severity' => $item->severity->value,
                    'severity_label' => $item->severity->label(),
                    'work_type' => $item->work_type->value,
                    'work_type_label' => $item->work_type->label(),
                    'cost' => [
                        'labor_low' => $item->labor_low,
                        'labor_high' => $item->labor_high,
                        'material_low' => $item->material_low,
                        'material_high' => $item->material_high,
                        'parts_low' => $item->parts_low,
                        'parts_high' => $item->parts_high,
                        'other_low' => $item->other_low,
                        'other_high' => $item->other_high,
                        'total_low' => $item->totalLow(),
                        'total_high' => $item->totalHigh(),
                        'currency' => 'IDR',
                    ],
                    'duration_min_hours' => $item->duration_min_hours,
                    'duration_max_hours' => $item->duration_max_hours,
                    'recommendation' => $item->recommendation,
                ])
                ->values()
            : [];

        return [
            'version' => $version->version,
            'low' => $version->total_low,
            'high' => $version->total_high,
            'currency' => 'IDR',
            'duration' => [
                'min_days' => $version->duration_min_days,
                'max_days' => $version->duration_max_days,
            ],
            'assumptions' => $version->assumptions,
            'items' => $items,
            'disclaimer' => $version->disclaimer,
            'published_at' => $version->published_at->toIso8601String(),
            'valid_until' => $this->valid_until?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function photo(BodyPaintDamagePhoto $photo): array
    {
        return [
            'id' => $photo->getKey(),
            'asset_id' => $photo->asset_id,
            'photo_type' => $photo->photo_type->value,
            'review_status' => $photo->review_status->value,
            'rejection_reason_code' => $photo->rejection_reason_code,
            'rejection_reason' => $photo->rejection_reason,
            'temporary_url' => $photo->relationLoaded('asset')
                ? $photo->asset->getTemporaryUrl()
                : null,
            'created_at' => $photo->created_at?->toIso8601String(),
        ];
    }
}
