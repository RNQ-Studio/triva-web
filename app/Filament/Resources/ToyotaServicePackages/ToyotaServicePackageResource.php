<?php

namespace App\Filament\Resources\ToyotaServicePackages;

use App\Filament\Resources\ToyotaServicePackages\Pages\CreateToyotaServicePackage;
use App\Filament\Resources\ToyotaServicePackages\Pages\EditToyotaServicePackage;
use App\Filament\Resources\ToyotaServicePackages\Pages\ListToyotaServicePackages;
use App\Filament\Resources\ToyotaServicePackages\Schemas\ToyotaServicePackageForm;
use App\Filament\Resources\ToyotaServicePackages\Tables\ToyotaServicePackagesTable;
use App\Models\ToyotaServicePackage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * "Data Paket reguler" yang diminta notulensi 19 Agustus 2026, menjadi dasar
 * simulasi biaya servis berkala di aplikasi pelanggan.
 */
class ToyotaServicePackageResource extends Resource
{
    protected static ?string $model = ToyotaServicePackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Paket Servis Berkala';

    protected static ?string $modelLabel = 'Paket servis berkala';

    protected static ?string $pluralModelLabel = 'Paket servis berkala';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ToyotaServicePackageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToyotaServicePackagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToyotaServicePackages::route('/'),
            'create' => CreateToyotaServicePackage::route('/create'),
            'edit' => EditToyotaServicePackage::route('/{record}/edit'),
        ];
    }
}
