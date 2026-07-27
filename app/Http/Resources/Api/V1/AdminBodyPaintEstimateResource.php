<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BodyPaintEstimate;
use App\Models\BodyPaintEstimateItem;
use App\Models\BodyPaintEstimateVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BodyPaintEstimate */
class AdminBodyPaintEstimateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $customer = (new BodyPaintEstimateResource($this->resource))
            ->toArray($request);

        return [
            ...$customer,
            'customer' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->getKey(),
                'name' => $this->user->name,
                'phone' => $this->user->phone,
                'city' => $this->user->city,
            ]),
            'assigned_estimator' => $this->assignedEstimator === null
                ? null
                : [
                    'id' => $this->assignedEstimator->getKey(),
                    'name' => $this->assignedEstimator->name,
                ],
            'sla' => [
                'due_at' => $this->due_at?->toIso8601String(),
                'is_overdue' => $this->isSlaOverdue(),
            ],
            'has_high_risk_damage' => $this->has_high_risk_damage,
            'engine_estimate' => [
                'low' => $this->engine_total_low,
                'high' => $this->engine_total_high,
                'currency' => 'IDR',
                'items' => $this->relationLoaded('items')
                    ? $this->items
                        ->whereNull('estimate_version')
                        ->map(
                            fn (BodyPaintEstimateItem $item): array => $this
                                ->internalItem($item),
                        )
                        ->values()
                    : [],
            ],
            'versions' => $this->relationLoaded('versions')
                ? $this->versions->map(
                    fn (BodyPaintEstimateVersion $version): array => [
                        'version' => $version->version,
                        'total_low' => $version->total_low,
                        'total_high' => $version->total_high,
                        'duration_min_days' => $version->duration_min_days,
                        'duration_max_days' => $version->duration_max_days,
                        'assumptions' => $version->assumptions,
                        'disclaimer' => $version->disclaimer,
                        'override_reason_code' => $version
                            ->override_reason_code,
                        'override_reason' => $version->override_reason,
                        'published_by' => $version->publisher->name,
                        'published_at' => $version->published_at
                            ->toIso8601String(),
                    ],
                )->values()->all()
                : [],
            'available_actions' => collect($this->availableAdminActions())
                ->map(fn ($action): array => [
                    'value' => $action->value,
                    'label' => $action->label(),
                ])
                ->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function internalItem(BodyPaintEstimateItem $item): array
    {
        return [
            'id' => $item->getKey(),
            'damage_id' => $item->damage_id,
            'panel_code' => $item->panel_code,
            'damage_type' => $item->damage_type,
            'severity' => $item->severity->value,
            'work_type' => $item->work_type->value,
            'labor_low' => $item->labor_low,
            'labor_high' => $item->labor_high,
            'material_low' => $item->material_low,
            'material_high' => $item->material_high,
            'parts_low' => $item->parts_low,
            'parts_high' => $item->parts_high,
            'other_low' => $item->other_low,
            'other_high' => $item->other_high,
            'duration_min_hours' => $item->duration_min_hours,
            'duration_max_hours' => $item->duration_max_hours,
            'recommendation' => $item->recommendation,
            'matrix_code' => $item->matrix_code,
            'matrix_version' => $item->matrix_version,
        ];
    }
}
