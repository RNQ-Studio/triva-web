<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

class AdminOtoxpertBookingResource extends OtoxpertBookingResource
{
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);
        $canUpdate = $request->user()?->can('manage', $this->resource) ?? false;

        return [
            ...$base,
            'customer' => [
                'id' => $this->user->getKey(),
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
                'city' => $this->user->city,
            ],
            'assigned_operator' => $this->assignedOperator === null
                ? null
                : [
                    'id' => $this->assignedOperator->getKey(),
                    'name' => $this->assignedOperator->name,
                    'email' => $this->assignedOperator->email,
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
                'follow_up_outcome' => $this->follow_up_outcome,
            ],
        ];
    }
}
