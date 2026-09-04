<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\ToyotaServiceConflictException;
use App\Http\Controllers\Controller;
use App\Models\ToyotaServiceBooking;
use App\Services\ToyotaServicePublicStatusService;
use App\Support\Enums\ToyotaServiceBookingStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Halaman publik bertoken untuk memperbarui status booking servis Toyota.
 *
 * Tautannya dikirim aplikasi di dalam pesan WhatsApp booking, sehingga PIC
 * cabang bisa menandai booking sedang diproses atau selesai dari ponsel tanpa
 * masuk ke panel admin. Token adalah satu-satunya kunci akses; token yang
 * tidak dikenal dijawab 404 tanpa membedakan apakah bookingnya ada.
 */
class ToyotaServiceBookingStatusController extends Controller
{
    public function __construct(
        private readonly ToyotaServicePublicStatusService $status,
    ) {}

    public function show(string $token): View
    {
        $booking = $this->booking($token);

        return view('toyota-service.status', $this->viewData($booking));
    }

    public function update(Request $request, string $token): RedirectResponse
    {
        $booking = $this->booking($token);
        $data = $request->validate([
            'stage' => ['required', Rule::in([
                ToyotaServicePublicStatusService::STAGE_PROCESSING,
                ToyotaServicePublicStatusService::STAGE_COMPLETED,
            ])],
        ]);

        try {
            $this->status->advance($booking, $data['stage']);
        } catch (ToyotaServiceConflictException $exception) {
            return redirect()
                ->route('toyota-service.status', $token)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('toyota-service.status', $token)
            ->with('success', 'Status booking berhasil diperbarui.');
    }

    private function booking(string $token): ToyotaServiceBooking
    {
        abort_unless(
            preg_match('/^[0-9a-fA-F-]{36}$/', $token) === 1,
            404,
        );

        return ToyotaServiceBooking::query()
            ->where('public_token', $token)
            ->with(['vehicle', 'serviceLocation', 'serviceType', 'user'])
            ->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function viewData(ToyotaServiceBooking $booking): array
    {
        $timezone = $booking->serviceLocation?->timezone ?? 'Asia/Jakarta';
        $slot = $booking->confirmed_start_at ?? $booking->primary_start_at;
        $slotEnd = $booking->confirmed_end_at ?? $booking->primary_end_at;

        return [
            'booking' => $booking,
            'stage' => $this->status->stageOf($booking),
            'availableStages' => $this->status->availableStages($booking),
            'stages' => [
                ToyotaServicePublicStatusService::STAGE_WAITING => 'Menunggu',
                ToyotaServicePublicStatusService::STAGE_PROCESSING => 'Diproses',
                ToyotaServicePublicStatusService::STAGE_COMPLETED => 'Selesai',
            ],
            'schedule' => $slot === null || $slotEnd === null ? null : sprintf(
                '%s, %s-%s',
                $slot->copy()->setTimezone($timezone)->translatedFormat('l, d F Y'),
                $slot->copy()->setTimezone($timezone)->format('H:i'),
                $slotEnd->copy()->setTimezone($timezone)->format('H:i'),
            ),
            'isClosed' => $booking->status->isTerminal()
                && $booking->status !== ToyotaServiceBookingStatus::Completed,
        ];
    }
}
