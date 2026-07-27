<?php

namespace App\Filament\Resources\Appraisals\Schemas;

use App\Models\Appraisal;
use App\Support\Enums\AppraisalStatus;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class AppraisalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Permintaan')
                ->columns(3)
                ->schema([
                    TextEntry::make('reference_no')->label('Referensi')->copyable(),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (AppraisalStatus $state): string => $state->customerLabel()),
                    TextEntry::make('submitted_at')->label('Dikirim')->dateTime()->placeholder('Draft'),
                    TextEntry::make('user.name')->label('Pelanggan'),
                    TextEntry::make('user.phone')->label('Nomor ponsel')->placeholder('Belum diisi'),
                    TextEntry::make('user.city')->label('Kota')->placeholder('Belum diisi'),
                ]),
            Section::make('Kendaraan')
                ->columns(3)
                ->schema([
                    TextEntry::make('vehicle.make')->label('Merek'),
                    TextEntry::make('vehicle.model')->label('Model'),
                    TextEntry::make('vehicle.variant')->label('Varian'),
                    TextEntry::make('vehicle.year')->label('Tahun'),
                    TextEntry::make('vehicle.transmission')->label('Transmisi'),
                    TextEntry::make('vehicle.mileage')
                        ->label('Kilometer')
                        ->formatStateUsing(fn (int $state): string => Number::format($state).' km'),
                ]),
            Section::make('Kondisi')
                ->columns(3)
                ->schema([
                    TextEntry::make('tax_status')->label('Pajak')->placeholder('Belum diisi'),
                    TextEntry::make('flood_history')->label('Riwayat banjir')->placeholder('Belum diisi'),
                    TextEntry::make('major_accident_history')->label('Tabrakan berat')->placeholder('Belum diisi'),
                    TextEntry::make('service_history')->label('Riwayat servis')->placeholder('Belum diisi'),
                    TextEntry::make('ownership')->label('Kepemilikan')->placeholder('Belum diisi'),
                    TextEntry::make('condition_percentage')
                        ->label('Kondisi saat ini')
                        ->suffix('%'),
                    TextEntry::make('current_photos_count')->label('Foto saat ini')->suffix(' / 5'),
                ]),
            Section::make('Hasil terbaru')
                ->columns(3)
                ->visible(fn (Appraisal $record): bool => $record->latestResult !== null)
                ->schema([
                    TextEntry::make('latestResult.trade_in_low')
                        ->label('Trade-in rendah')
                        ->money('IDR'),
                    TextEntry::make('latestResult.trade_in_high')
                        ->label('Trade-in tinggi')
                        ->money('IDR'),
                    TextEntry::make('latestResult.comparable_count')
                        ->label('Pembanding'),
                    TextEntry::make('latestResult.confidence')
                        ->label('Confidence')
                        ->badge(),
                    TextEntry::make('latestResult.data_as_of')
                        ->label('Data per')
                        ->dateTime(),
                    TextEntry::make('latestResult.valid_until')
                        ->label('Berlaku hingga')
                        ->dateTime(),
                ]),
            Section::make('Rekomendasi engine')
                ->description('Hanya untuk back-office; customer baru melihat hasil setelah diterbitkan appraiser.')
                ->columns(3)
                ->visible(fn (Appraisal $record): bool => $record->latestMarketEstimate !== null)
                ->schema([
                    TextEntry::make('latestMarketEstimate.status')
                        ->label('Status engine')
                        ->badge(),
                    TextEntry::make('latestMarketEstimate.market_mid')
                        ->label('Median pasar')
                        ->money('IDR')
                        ->placeholder('Belum tersedia'),
                    TextEntry::make('latestMarketEstimate.trade_in_low')
                        ->label('Trade-in rendah')
                        ->money('IDR')
                        ->placeholder('Belum tersedia'),
                    TextEntry::make('latestMarketEstimate.trade_in_high')
                        ->label('Trade-in tinggi')
                        ->money('IDR')
                        ->placeholder('Belum tersedia'),
                    TextEntry::make('latestMarketEstimate.comparable_count')
                        ->label('Pembanding valid'),
                    TextEntry::make('latestMarketEstimate.confidence')
                        ->label('Confidence')
                        ->badge(),
                    TextEntry::make('latestMarketEstimate.provider_codes')
                        ->label('Provider')
                        ->listWithLineBreaks()
                        ->placeholder('Tidak ada provider aktif'),
                    TextEntry::make('latestMarketEstimate.data_as_of')
                        ->label('Data per')
                        ->dateTime()
                        ->placeholder('Belum tersedia'),
                    TextEntry::make('latestMarketEstimate.failure_code')
                        ->label('Kode fallback')
                        ->placeholder('Tidak ada'),
                ]),
            Section::make('Audit pembanding engine')
                ->description('Semua snapshot disimpan; item duplikat, outlier, atau tidak relevan tetap terlihat dengan alasan eksklusi.')
                ->visible(fn (Appraisal $record): bool => $record->latestMarketEstimate?->comparables->isNotEmpty() === true)
                ->schema([
                    RepeatableEntry::make('latestMarketEstimate.comparables')
                        ->hiddenLabel()
                        ->columns(4)
                        ->schema([
                            TextEntry::make('source_code')
                                ->label('Sumber')
                                ->badge(),
                            TextEntry::make('listing_price')
                                ->label('Harga listing')
                                ->money('IDR'),
                            TextEntry::make('variant')
                                ->label('Unit')
                                ->placeholder('Varian tidak tersedia'),
                            TextEntry::make('year')
                                ->label('Tahun'),
                            TextEntry::make('mileage')
                                ->label('Kilometer')
                                ->numeric()
                                ->suffix(' km')
                                ->placeholder('Tidak tersedia'),
                            TextEntry::make('city')
                                ->label('Lokasi')
                                ->placeholder('Tidak tersedia'),
                            TextEntry::make('similarity_score')
                                ->label('Similarity'),
                            TextEntry::make('exclusion_reason')
                                ->label('Status seleksi')
                                ->badge()
                                ->formatStateUsing(
                                    fn (?string $state): string => $state ?? 'valid',
                                ),
                        ]),
                ]),
        ]);
    }
}
