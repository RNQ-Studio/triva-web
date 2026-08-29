<?php

namespace App\Http\Requests\Api\V1;

use App\Models\VisitEvent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dashboard analitik admin memakai kemampuan yang sama dengan statistik
 * kunjungan: siapa pun yang boleh melihat analitik boleh melihat seluruh
 * ringkasannya.
 */
class AdminAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', VisitEvent::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
