<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CreditSimulation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestCreditFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        $simulation = $this->route('simulation');

        return $simulation instanceof CreditSimulation
            && $simulation->user_id === $this->user()?->getKey();
    }

    public function rules(): array
    {
        return [
            'follow_up_consent' => ['accepted'],
            'consent_version' => [
                'required',
                'string',
                Rule::in(['credit-follow-up-v1']),
            ],
            'contact_channel' => [
                'required',
                Rule::in(['whatsapp', 'phone', 'email']),
            ],
            'campaign_source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
