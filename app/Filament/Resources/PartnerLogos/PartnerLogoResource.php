<?php

namespace App\Filament\Resources\PartnerLogos;

use App\Filament\Resources\PartnerLogos\Pages\CreatePartnerLogo;
use App\Filament\Resources\PartnerLogos\Pages\EditPartnerLogo;
use App\Filament\Resources\PartnerLogos\Pages\ListPartnerLogos;
use App\Filament\Resources\PartnerLogos\Schemas\PartnerLogoForm;
use App\Filament\Resources\PartnerLogos\Tables\PartnerLogosTable;
use App\Models\PartnerLogo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Logo "Mitra resmi" di beranda aplikasi (revisi 6 Oktober 2026). Juga
 * dikelola dari Admin Panel aplikasi.
 */
class PartnerLogoResource extends Resource
{
    protected static ?string $model = PartnerLogo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $navigationLabel = 'Mitra Resmi';

    protected static ?string $modelLabel = 'Mitra resmi';

    protected static ?string $pluralModelLabel = 'Mitra Resmi';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PartnerLogoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PartnerLogosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPartnerLogos::route('/'),
            'create' => CreatePartnerLogo::route('/create'),
            'edit' => EditPartnerLogo::route('/{record}/edit'),
        ];
    }
}
