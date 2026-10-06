<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ToyotaServicePackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Paket servis berkala (budget jasa & part T-Care) untuk Admin Panel
 * aplikasi.
 *
 * @mixin ToyotaServicePackage
 */
class AdminToyotaServicePackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $today = now('Asia/Jakarta')->toDateString();

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'vehicle_model' => $this->vehicle_model,
            'km_interval' => $this->km_interval,
            'labor_cost' => $this->labor_cost,
            'parts_cost' => $this->parts_cost,
            'total_cost' => $this->totalCost(),
            'includes' => $this->includes ?? [],
            'duration_min_minutes' => $this->duration_min_minutes,
            'duration_max_minutes' => $this->duration_max_minutes,
            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'source_reference' => $this->source_reference,
            // Sama dengan scope `effective()`: hanya paket ini yang dipakai
            // simulasi biaya servis hari ini.
            'is_effective' => $this->is_active
                && $this->effective_from->toDateString() <= $today
                && ($this->effective_to === null || $this->effective_to->toDateString() >= $today),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
