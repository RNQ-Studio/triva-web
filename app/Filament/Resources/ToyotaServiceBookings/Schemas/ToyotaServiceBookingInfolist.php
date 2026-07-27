<?php

namespace App\Filament\Resources\ToyotaServiceBookings\Schemas;

use App\Models\ToyotaServiceBooking;
use App\Support\Enums\ToyotaServiceBookingStatus;
use App\Support\Enums\ToyotaServiceFulfillmentType;
use App\Support\Enums\VehicleBenefitStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ToyotaServiceBookingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking')
                ->columns(3)
                ->schema([
                    TextEntry::make('reference_no')->label('Referensi')->copyable(),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(
                            fn (ToyotaServiceBookingStatus $state): string => $state->customerLabel()
                        ),
                    TextEntry::make('serviceType.name')->label('Layanan'),
                    TextEntry::make('fulfillment_type')
                        ->label('Cara servis')
                        ->formatStateUsing(
                            fn (ToyotaServiceFulfillmentType $state): string => $state->label()
                        ),
                    TextEntry::make('serviceLocation.name')->label('Lokasi'),
                    TextEntry::make('assignedServiceAdvisor.name')
                        ->label('Service Advisor')
                        ->placeholder('Belum ditetapkan'),
                    TextEntry::make('active_slot_start_at')
                        ->label('Jadwal aktif')
                        ->state(fn (ToyotaServiceBooking $record): string => $record
                            ->active_slot_start_at
                            ->timezone($record->serviceLocation->timezone)
                            ->format('d M Y H:i').' - '.$record
                            ->active_slot_end_at
                            ->timezone($record->serviceLocation->timezone)
                            ->format('H:i T')),
                    TextEntry::make('due_at')
                        ->label('Target konfirmasi (waktu lokasi)')
                        ->state(fn (ToyotaServiceBooking $record): string => $record
                            ->due_at
                            ->timezone($record->serviceLocation->timezone)
                            ->format('d M Y H:i T')),
                    TextEntry::make('external_booking_number')
                        ->label('Nomor booking dealer')
                        ->placeholder('Belum tersedia'),
                ]),
            Section::make('Pelanggan dan kendaraan')
                ->columns(3)
                ->schema([
                    TextEntry::make('user.name')->label('Pelanggan'),
                    TextEntry::make('user.email')->label('Email')->copyable(),
                    TextEntry::make('user.phone')->label('Nomor ponsel')->copyable(),
                    TextEntry::make('vehicle.make')->label('Merek'),
                    TextEntry::make('vehicle.model')->label('Model'),
                    TextEntry::make('vehicle.license_plate')->label('Plat nomor')->copyable(),
                    TextEntry::make('current_mileage')->label('Kilometer')->suffix(' km'),
                    TextEntry::make('complaint')
                        ->label('Keluhan / kebutuhan')
                        ->columnSpanFull(),
                ]),
            Section::make('Konfirmasi operasional')
                ->columns(2)
                ->schema([
                    TextEntry::make('pic_name')->label('PIC')->placeholder('Belum tersedia'),
                    TextEntry::make('arrival_instructions')
                        ->label('Instruksi kedatangan')
                        ->placeholder('Belum tersedia'),
                    TextEntry::make('reason_code')->label('Kode alasan')->placeholder('-'),
                    TextEntry::make('reason')->label('Alasan')->placeholder('-'),
                    TextEntry::make('ths_address')
                        ->label('Alamat THS')
                        ->visible(fn (ToyotaServiceBooking $record): bool => $record
                            ->fulfillment_type
                            ->value === 'ths')
                        ->columnSpanFull(),
                ]),
            Section::make('Benefit kendaraan')
                ->schema([
                    RepeatableEntry::make('benefitChecks')
                        ->label('')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('benefit_type')->label('Benefit'),
                            TextEntry::make('status')
                                ->label('Status')
                                ->badge()
                                ->formatStateUsing(
                                    fn (VehicleBenefitStatus $state): string => $state->label()
                                ),
                            TextEntry::make('valid_until')
                                ->label('Berlaku hingga (WIB)')
                                ->formatStateUsing(
                                    fn ($state): string => $state
                                        ->timezone('Asia/Jakarta')
                                        ->format('d M Y H:i T')
                                )
                                ->placeholder('-'),
                            TextEntry::make('verifiedBy.name')
                                ->label('Diverifikasi oleh')
                                ->placeholder('-'),
                        ]),
                ]),
            Section::make('Timeline')
                ->schema([
                    RepeatableEntry::make('statusHistories')
                        ->label('')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Waktu (WIB)')
                                ->formatStateUsing(
                                    fn ($state): string => $state
                                        ->timezone('Asia/Jakarta')
                                        ->format('d M Y H:i T')
                                ),
                            TextEntry::make('title')->label('Event'),
                            TextEntry::make('description')->label('Keterangan')->placeholder('-'),
                            TextEntry::make('changedBy.name')
                                ->label('Aktor')
                                ->placeholder('Sistem'),
                        ]),
                ]),
        ]);
    }
}
