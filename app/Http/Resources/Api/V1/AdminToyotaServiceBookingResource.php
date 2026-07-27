<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class AdminToyotaServiceBookingResource extends ToyotaServiceBookingResource
{
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);
        $canUpdate = $request->user()?->can('service_bookings.update') ?? false;

        return [
            ...$base,
            'benefit_checks' => AdminVehicleBenefitCheckResource::collection(
                $this->whenLoaded('benefitChecks')
            ),
            'customer' => [
                'id' => $this->user->getKey(),
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'city' => $this->user->city,
            ],
            'assigned_advisor' => $this->assignedServiceAdvisor === null ? null : [
                'id' => $this->assignedServiceAdvisor->getKey(),
                'name' => $this->assignedServiceAdvisor->name,
                'email' => $this->assignedServiceAdvisor->email,
            ],
            'available_admin_actions' => $canUpdate
                ? collect($this->availableAdminActions())
                    ->map(fn ($action): array => [
                        'action' => $action->value,
                        'label' => $action->label(),
                    ])
                    ->values()
                    ->all()
                : [],
            'sla' => [
                'is_overdue' => $this->isSlaOverdue(),
                'due_at' => $this->due_at->toIso8601String(),
                'overdue_minutes' => $this->isSlaOverdue()
                    ? (int) $this->due_at->diffInMinutes(now())
                    : 0,
            ],
            'campaign' => [
                'source' => $this->campaign_source,
                'metadata' => $this->campaign_metadata,
            ],
        ];
    }
}
