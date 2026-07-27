<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AppraisalResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AppraisalResult */
class AppraisalResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'market_price' => [
                'low' => $this->market_low,
                'mid' => $this->market_mid,
                'high' => $this->market_high,
                'currency' => 'IDR',
            ],
            'trade_in_estimate' => [
                'low' => $this->trade_in_low,
                'high' => $this->trade_in_high,
                'currency' => 'IDR',
            ],
            'confidence' => $this->confidence->value,
            'comparable_count' => $this->comparable_count,
            'data_as_of' => $this->data_as_of->toIso8601String(),
            'valid_until' => $this->valid_until->toIso8601String(),
            'is_expired' => $this->valid_until->isPast(),
            'requires_physical_inspection' => $this->requires_physical_inspection,
            'disclaimer' => $this->disclaimer,
            'adjustments' => $this->adjustments ?? [],
            'sources' => $this->sourceSummary(),
            'publication_type' => $this->publication_type,
            'published_at' => $this->published_at->toIso8601String(),
        ];
    }

    /** @return list<array{code: string, label: string, comparable_count: int}> */
    private function sourceSummary(): array
    {
        if (! $this->relationLoaded('comparables')) {
            return [];
        }

        return $this->comparables
            ->where('is_outlier', false)
            ->groupBy('source_code')
            ->map(fn ($comparables, string $code): array => [
                'code' => $code,
                'label' => match ($code) {
                    'olx_approved_html' => 'OLX (akses berizin)',
                    'partner_feed' => 'Feed partner',
                    'approved_csv' => 'Dataset terkurasi',
                    'manual_appraiser' => 'Penilaian appraiser',
                    default => 'Sumber terverifikasi',
                },
                'comparable_count' => $comparables->count(),
            ])
            ->values()
            ->all();
    }
}
