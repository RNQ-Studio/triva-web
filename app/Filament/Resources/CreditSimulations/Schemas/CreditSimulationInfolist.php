<?php

namespace App\Filament\Resources\CreditSimulations\Schemas;

use App\Support\Enums\CreditSimulationStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreditSimulationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Simulasi')->columns(3)->schema([
                TextEntry::make('reference_no')
                    ->label('Referensi')
                    ->copyable(),
                TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(
                        fn (CreditSimulationStatus $state): string => $state
                            ->label()
                    ),
                TextEntry::make('saved_at')
                    ->label('Disimpan')
                    ->dateTime('d M Y H:i'),
                TextEntry::make('user.name')->label('Pelanggan'),
                TextEntry::make('user.phone')
                    ->label('Nomor ponsel')
                    ->placeholder('Belum diisi'),
                TextEntry::make('campaign_source')
                    ->label('Sumber campaign')
                    ->placeholder('Tidak dicatat'),
            ]),
            Section::make('Program snapshot')->columns(3)->schema([
                TextEntry::make('program_snapshot.partner_name')
                    ->label('Partner'),
                TextEntry::make('program_snapshot.program_name')
                    ->label('Program'),
                TextEntry::make('program_snapshot.version')
                    ->label('Versi'),
                TextEntry::make('program_snapshot.city')->label('Kota OTR'),
                TextEntry::make('program_snapshot.vehicle_model')
                    ->label('Model'),
                TextEntry::make('program_snapshot.vehicle_variant')
                    ->label('Varian'),
                TextEntry::make('otr_price')->label('OTR')->money('IDR'),
                TextEntry::make('formula_version')
                    ->label('Formula version'),
                TextEntry::make('valid_until')
                    ->label('Berlaku hingga')
                    ->date()
                    ->placeholder('Tanpa batas akhir'),
                TextEntry::make('program_snapshot.source_reference')
                    ->label('Provenance')
                    ->columnSpanFull(),
            ]),
            Section::make('Dana awal dan appraisal')->columns(3)->schema([
                TextEntry::make('cash_down_payment')
                    ->label('DP tunai')
                    ->money('IDR'),
                TextEntry::make('approved_discount')
                    ->label('Diskon disetujui')
                    ->money('IDR'),
                TextEntry::make('total_down_payment')
                    ->label('Total DP')
                    ->money('IDR'),
                TextEntry::make('appraisal.reference_no')
                    ->label('Sumber appraisal')
                    ->placeholder('Input trade-in manual/tanpa trade-in'),
                TextEntry::make('trade_in_value')
                    ->label('Nilai trade-in')
                    ->money('IDR'),
                TextEntry::make('old_vehicle_payoff')
                    ->label('Sisa pelunasan')
                    ->money('IDR'),
                TextEntry::make('trade_in_equity')
                    ->label('Ekuitas trade-in')
                    ->money('IDR'),
                TextEntry::make('use_trade_in_as_dp')
                    ->label('Trade-in menjadi DP')
                    ->formatStateUsing(
                        fn (bool $state): string => $state ? 'Ya' : 'Tidak'
                    ),
            ]),
            Section::make('Perhitungan snapshot')->columns(3)->schema([
                TextEntry::make('principal')
                    ->label('Pokok pembiayaan')
                    ->money('IDR'),
                TextEntry::make('tenor_months')
                    ->label('Tenor')
                    ->suffix(' bulan'),
                TextEntry::make('annual_flat_rate_basis_points')
                    ->label('Bunga flat')
                    ->formatStateUsing(
                        fn (int $state): string => number_format(
                            $state / 100,
                            2,
                            ',',
                            '.',
                        ).'% per tahun'
                    ),
                TextEntry::make('total_flat_interest')
                    ->label('Total bunga')
                    ->money('IDR'),
                TextEntry::make('monthly_installment')
                    ->label('Cicilan bulanan')
                    ->money('IDR'),
                TextEntry::make('initial_payment')
                    ->label('Total dana awal')
                    ->money('IDR'),
                TextEntry::make('administration_fee')
                    ->label('Administrasi')
                    ->money('IDR'),
                TextEntry::make('provision_fee')
                    ->label('Provisi')
                    ->money('IDR'),
                TextEntry::make('upfront_insurance')
                    ->label('Asuransi awal')
                    ->money('IDR'),
                TextEntry::make('other_upfront_costs')
                    ->label('Biaya lain')
                    ->money('IDR'),
                TextEntry::make('total_payment')
                    ->label('Total pembayaran estimasi')
                    ->money('IDR'),
            ]),
            Section::make('Follow-up')->columns(3)->schema([
                TextEntry::make('followUpLead.reference_no')
                    ->label('Referensi lead')
                    ->placeholder('Belum diminta'),
                TextEntry::make('followUpLead.status')
                    ->label('Status lead')
                    ->placeholder('Belum diminta'),
                TextEntry::make('followUpLead.assignedSales.name')
                    ->label('Sales')
                    ->placeholder('Belum ditetapkan'),
            ]),
        ]);
    }
}
