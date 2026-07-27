<?php

namespace App\Services;

use App\Models\CreditFollowUpLead;
use App\Models\CreditSimulation;
use App\Models\User;
use App\Support\Enums\CreditLeadStatus;
use App\Support\Enums\CreditSimulationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreditFollowUpService
{
    public function __construct(
        private readonly CreditSimulationNotificationService $notifications,
        private readonly CreditSimulationCreationService $simulations,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{simulation: CreditSimulation, replayed: bool}
     */
    public function request(
        CreditSimulation $simulation,
        User $user,
        array $data,
    ): array {
        if (in_array(
            $data['contact_channel'],
            ['whatsapp', 'phone'],
            true,
        ) && blank($user->phone)) {
            throw ValidationException::withMessages([
                'contact_channel' => [
                    'Lengkapi nomor ponsel profil untuk channel ini.',
                ],
            ]);
        }

        $replayed = false;
        DB::transaction(function () use (
            $simulation,
            $user,
            $data,
            &$replayed,
        ): void {
            $locked = CreditSimulation::query()
                ->whereKey($simulation)
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $existing = CreditFollowUpLead::query()
                ->where('simulation_id', $locked->getKey())
                ->first();
            if ($existing !== null) {
                $replayed = true;

                return;
            }

            CreditFollowUpLead::query()->create([
                'reference_no' => 'SKL-'.now('Asia/Jakarta')->format('ymd').'-'
                    .strtoupper(substr((string) Str::ulid(), -8)),
                'simulation_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
                'status' => CreditLeadStatus::New,
                'contact_channel' => $data['contact_channel'],
                'consent_version' => $data['consent_version'],
                'consent_at' => now(),
                'campaign_source' => $data['campaign_source']
                    ?? $locked->campaign_source,
            ]);
            $locked->update([
                'status' => CreditSimulationStatus::LeadCreated,
            ]);
            $this->notifications->followUpCreated($locked);
        }, 3);

        return [
            'simulation' => $this->simulations->load($simulation->refresh()),
            'replayed' => $replayed,
        ];
    }
}
