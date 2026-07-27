<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OtoxpertBooking;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin OtoxpertBooking */
class OtoxpertBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $timezone = $this->workshop->timezone;

        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'provider' => 'otoxpert',
            'status' => $this->status->value,
            'status_label' => $this->status->customerLabel(),
            'allowed_customer_actions' => $this->allowedCustomerActions(),
            'is_confirmed' => $this->confirmed_at !== null,
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
            'workshop' => new OtoxpertWorkshopResource(
                $this->whenLoaded('workshop')
            ),
            'service' => new OtoxpertServiceResource(
                $this->whenLoaded('service')
            ),
            'current_mileage' => $this->current_mileage,
            'last_service_date' => $this->last_service_date?->toDateString(),
            'complaint' => $this->complaint,
            'symptom_codes' => $this->symptom_codes,
            'pickup_delivery_requested' => $this->pickup_delivery_requested,
            'contact_channel' => $this->contact_channel->value,
            'contact_channel_label' => $this->contact_channel->label(),
            'requested_slots' => [
                'primary' => $this->slot(
                    $this->primary_start_at,
                    $this->primary_end_at,
                    $timezone,
                ),
                'alternative' => $this->slot(
                    $this->alternative_start_at,
                    $this->alternative_end_at,
                    $timezone,
                ),
            ],
            'proposed_slot' => $this->proposed_start_at === null ? null : [
                ...$this->slot(
                    $this->proposed_start_at,
                    $this->proposed_end_at,
                    $timezone,
                ),
                'context' => $this->proposal_context,
                'reason' => $this->proposal_reason,
                'expires_at' => $this->proposal_expires_at?->toIso8601String(),
            ],
            'confirmed_slot' => $this->slot(
                $this->confirmed_start_at,
                $this->confirmed_end_at,
                $timezone,
            ),
            'reschedule_request' => $this->reschedule_primary_start_at === null
                ? null
                : [
                    'primary' => $this->slot(
                        $this->reschedule_primary_start_at,
                        $this->reschedule_primary_end_at,
                        $timezone,
                    ),
                    'alternative' => $this->slot(
                        $this->reschedule_alternative_start_at,
                        $this->reschedule_alternative_end_at,
                        $timezone,
                    ),
                    'reason' => $this->reschedule_reason,
                ],
            'photos' => $this->whenLoaded(
                'photos',
                fn () => $this->photos->map(fn ($photo): array => [
                    'id' => $photo->id,
                    'asset_id' => $photo->asset_id,
                    'url' => $photo->asset->getTemporaryUrl(),
                    'mime_type' => $photo->asset->mime_type,
                    'original_filename' => $photo->asset->original_filename,
                ])->values()->all(),
            ),
            'timeline' => $this->whenLoaded(
                'statusHistories',
                fn () => $this->statusHistories->map(fn ($history): array => [
                    'id' => $history->id,
                    'status' => $history->status->value,
                    'event' => $history->event,
                    'title' => $history->title,
                    'description' => $history->description,
                    'reason_code' => $history->reason_code,
                    'actor_type' => $history->actor_type,
                    'metadata' => $history->metadata,
                    'created_at' => $history->created_at?->toIso8601String(),
                ])->values()->all(),
            ),
            'operator' => $this->confirmed_at === null ? null : [
                'name' => $this->pic_name ?? $this->assignedOperator?->name,
                'phone' => $this->workshop->phone,
            ],
            'arrival_instructions' => $this->arrival_instructions,
            'external_booking_number' => $this->external_booking_number,
            'price' => $this->quoted_price_min === null ? null : [
                'type' => $this->quoted_price_type,
                'minimum_amount' => $this->quoted_price_min,
                'maximum_amount' => $this->quoted_price_max,
                'currency' => $this->quoted_price_currency,
                'source' => $this->quoted_price_source,
                'valid_until' => $this->quoted_price_valid_until?->toDateString(),
                'is_final' => false,
                'disclaimer' => 'Harga final ditentukan bengkel setelah pemeriksaan kendaraan.',
            ],
            'reason_code' => $this->reason_code,
            'reason' => $this->reason,
            'campaign_source' => $this->campaign_source,
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'due_at' => $this->due_at->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     date: string,
     *     time_window: string,
     *     timezone: string,
     *     start_at: string,
     *     end_at: string
     * }|null
     */
    protected function slot(
        ?Carbon $start,
        ?Carbon $end,
        string $timezone,
    ): ?array {
        if ($start === null || $end === null) {
            return null;
        }

        $localStart = $start->copy()->setTimezone($timezone);
        $localEnd = $end->copy()->setTimezone($timezone);

        return [
            'date' => $localStart->toDateString(),
            'time_window' => $localStart->format('H:i')
                .'-'.$localEnd->format('H:i'),
            'timezone' => $timezone,
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
        ];
    }
}
