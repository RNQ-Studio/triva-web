<?php

namespace App\Http\Resources\Api\V1;

use App\Models\SalesContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SalesContact */
class SalesContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'whatsapp_number' => SalesContact::normalizeWhatsapp($this->whatsapp_number),
            'photo_url' => $this->photoUrl(),
            'sort_order' => $this->sort_order,
        ];
    }
}
