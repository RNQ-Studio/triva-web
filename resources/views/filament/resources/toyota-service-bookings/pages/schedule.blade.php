<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-wrap items-end gap-3">
                <x-filament::button color="gray" icon="heroicon-o-chevron-left" wire:click="previousDay">
                    Sebelumnya
                </x-filament::button>

                <label class="min-w-52">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Tanggal servis (WIB)
                    </span>
                    <input
                        type="date"
                        wire:model.live="date"
                        class="block w-full rounded-lg border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                    />
                </label>

                <x-filament::button color="gray" wire:click="today">
                    Hari ini
                </x-filament::button>

                <x-filament::button color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="nextDay">
                    Berikutnya
                </x-filament::button>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">
                Jadwal {{ $this->formattedDate }}
            </x-slot>

            @if ($this->schedule->isEmpty())
                <div class="py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    Belum ada booking pada tanggal ini.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-3 py-3">Waktu</th>
                                <th class="px-3 py-3">Referensi</th>
                                <th class="px-3 py-3">Pelanggan / Kendaraan</th>
                                <th class="px-3 py-3">Layanan</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-3 py-3">Advisor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($this->schedule as $booking)
                                @php($timezone = $booking->serviceLocation->timezone)
                                <tr wire:key="schedule-{{ $booking->id }}" class="hover:bg-gray-50 dark:hover:bg-white/5">
                                    <td class="whitespace-nowrap px-3 py-4 font-semibold text-gray-950 dark:text-white">
                                        {{ $booking->active_slot_start_at->timezone($timezone)->format('H:i') }}
                                        -
                                        {{ $booking->active_slot_end_at->timezone($timezone)->format('H:i') }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-4">
                                        <a
                                            href="{{ \App\Filament\Resources\ToyotaServiceBookings\ToyotaServiceBookingResource::getUrl('view', ['record' => $booking]) }}"
                                            class="font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            {{ $booking->reference_no }}
                                        </a>
                                    </td>
                                    <td class="px-3 py-4">
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $booking->user->name }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">{{ $booking->vehicle->license_plate }}</div>
                                    </td>
                                    <td class="px-3 py-4">
                                        <div>{{ $booking->serviceType->name }}</div>
                                        <div class="text-gray-500 dark:text-gray-400">{{ $booking->fulfillment_type->label() }}</div>
                                    </td>
                                    <td class="px-3 py-4">{{ $booking->status->customerLabel() }}</td>
                                    <td class="px-3 py-4">{{ $booking->assignedServiceAdvisor?->name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
