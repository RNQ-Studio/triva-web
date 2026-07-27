<?php

namespace App\Filament\Resources\BodyPaintEstimates\Schemas;

use App\Support\BodyPaintCatalog;
use App\Support\Enums\BodyPaintEstimateStatus;
use App\Support\Enums\BodyPaintPhotoReviewStatus;
use App\Support\Enums\BodyPaintSeverity;
use App\Support\Enums\BodyPaintWorkType;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BodyPaintEstimateInfolist
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
                        ->formatStateUsing(
                            fn (BodyPaintEstimateStatus $state): string => $state->label(),
                        ),
                    TextEntry::make('due_at')
                        ->label('Target SLA')
                        ->dateTime('d M Y H:i')
                        ->placeholder('Belum disubmit'),
                    TextEntry::make('user.name')->label('Pelanggan'),
                    TextEntry::make('user.phone')
                        ->label('Nomor ponsel')
                        ->placeholder('Belum diisi'),
                    TextEntry::make('assignedEstimator.name')
                        ->label('Estimator')
                        ->placeholder('Belum ditetapkan'),
                    TextEntry::make('serviceLocation.name')
                        ->label('Cabang')
                        ->placeholder('Belum dipilih'),
                    TextEntry::make('customer_notes')
                        ->label('Catatan pelanggan')
                        ->placeholder('-')
                        ->columnSpan(2),
                ]),
            Section::make('Kendaraan')
                ->columns(4)
                ->schema([
                    TextEntry::make('vehicle.make')->label('Merek'),
                    TextEntry::make('vehicle.model')->label('Model'),
                    TextEntry::make('vehicle.variant')->label('Varian')->placeholder('-'),
                    TextEntry::make('vehicle.year')->label('Tahun'),
                    TextEntry::make('vehicle.license_plate')
                        ->label('Plat nomor')
                        ->copyable(),
                    TextEntry::make('vehicle.mileage')
                        ->label('Kilometer')
                        ->numeric()
                        ->suffix(' km'),
                    TextEntry::make('has_high_risk_damage')
                        ->label('Risiko tinggi')
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Ya' : 'Tidak')
                        ->badge()
                        ->color(fn (bool $state): string => $state ? 'danger' : 'success'),
                    TextEntry::make('requires_physical_inspection')
                        ->label('Inspeksi fisik')
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Wajib' : 'Tidak wajib')
                        ->badge(),
                ]),
            Section::make('Panel dan kerusakan')
                ->schema([
                    RepeatableEntry::make('damages')
                        ->label('')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('panel_code')
                                ->label('Panel')
                                ->formatStateUsing(
                                    fn (string $state): string => BodyPaintCatalog::PANELS[$state] ?? $state,
                                ),
                            TextEntry::make('damage_type')
                                ->label('Kerusakan')
                                ->formatStateUsing(
                                    fn (string $state): string => BodyPaintCatalog::DAMAGE_TYPES[$state] ?? $state,
                                ),
                            TextEntry::make('customer_severity')
                                ->label('Severity pelanggan')
                                ->formatStateUsing(
                                    fn (BodyPaintSeverity $state): string => $state->label(),
                                ),
                            TextEntry::make('estimator_severity')
                                ->label('Severity estimator')
                                ->formatStateUsing(
                                    fn (?BodyPaintSeverity $state): string => $state?->label() ?? '-',
                                ),
                            TextEntry::make('customer_note')
                                ->label('Catatan')
                                ->placeholder('-')
                                ->columnSpanFull(),
                        ]),
                ]),
            Section::make('Galeri foto')
                ->description('Foto dilindungi dan URL pratinjau bersifat sementara.')
                ->schema([
                    RepeatableEntry::make('photos')
                        ->label('')
                        ->columns(4)
                        ->schema([
                            ImageEntry::make('preview')
                                ->label('Foto')
                                ->state(fn ($record): ?string => $record->asset->getTemporaryUrl())
                                ->height(180)
                                ->square(),
                            TextEntry::make('asset.original_filename')
                                ->label('File')
                                ->url(fn ($record): ?string => $record->asset->getTemporaryUrl())
                                ->openUrlInNewTab(),
                            TextEntry::make('photo_type')->label('Jenis')->badge(),
                            TextEntry::make('review_status')
                                ->label('Review')
                                ->badge()
                                ->formatStateUsing(
                                    fn (BodyPaintPhotoReviewStatus $state): string => ucfirst($state->value),
                                ),
                            TextEntry::make('rejection_reason')
                                ->label('Alasan ditolak')
                                ->placeholder('-')
                                ->columnSpanFull(),
                        ]),
                ]),
            Section::make('Estimasi biaya')
                ->columns(4)
                ->schema([
                    TextEntry::make('engine_total_low')
                        ->label('Engine rendah')
                        ->money('IDR')
                        ->placeholder('-'),
                    TextEntry::make('engine_total_high')
                        ->label('Engine tinggi')
                        ->money('IDR')
                        ->placeholder('-'),
                    TextEntry::make('published_total_low')
                        ->label('Terbit rendah')
                        ->money('IDR')
                        ->placeholder('-'),
                    TextEntry::make('published_total_high')
                        ->label('Terbit tinggi')
                        ->money('IDR')
                        ->placeholder('-'),
                    RepeatableEntry::make('items')
                        ->label('Item engine dan versi')
                        ->columns(5)
                        ->schema([
                            TextEntry::make('panel_code')
                                ->label('Panel')
                                ->formatStateUsing(
                                    fn (string $state): string => BodyPaintCatalog::PANELS[$state] ?? $state,
                                ),
                            TextEntry::make('work_type')
                                ->label('Pekerjaan')
                                ->formatStateUsing(
                                    fn (BodyPaintWorkType $state): string => $state->label(),
                                ),
                            TextEntry::make('severity')
                                ->label('Severity')
                                ->formatStateUsing(
                                    fn (BodyPaintSeverity $state): string => $state->label(),
                                ),
                            TextEntry::make('total_low')
                                ->label('Rendah')
                                ->state(fn ($record): int => $record->totalLow())
                                ->money('IDR'),
                            TextEntry::make('total_high')
                                ->label('Tinggi')
                                ->state(fn ($record): int => $record->totalHigh())
                                ->money('IDR'),
                        ])
                        ->columnSpanFull(),
                ]),
            Section::make('Timeline')
                ->schema([
                    RepeatableEntry::make('statusHistories')
                        ->label('')
                        ->columns(4)
                        ->schema([
                            TextEntry::make('created_at')
                                ->label('Waktu')
                                ->dateTime('d M Y H:i'),
                            TextEntry::make('title')->label('Event'),
                            TextEntry::make('description')
                                ->label('Keterangan')
                                ->placeholder('-'),
                            TextEntry::make('changedBy.name')
                                ->label('Aktor')
                                ->placeholder('Sistem'),
                        ]),
                ]),
        ]);
    }
}
