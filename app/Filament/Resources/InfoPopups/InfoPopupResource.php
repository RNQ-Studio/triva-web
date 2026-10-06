<?php

namespace App\Filament\Resources\InfoPopups;

use App\Filament\Resources\InfoPopups\Pages\CreateInfoPopup;
use App\Filament\Resources\InfoPopups\Pages\EditInfoPopup;
use App\Filament\Resources\InfoPopups\Pages\ListInfoPopups;
use App\Filament\Resources\InfoPopups\Schemas\InfoPopupForm;
use App\Filament\Resources\InfoPopups\Tables\InfoPopupsTable;
use App\Models\InfoPopup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Popup informasi bergambar di beranda aplikasi (revisi 6 Oktober 2026).
 * Juga dikelola dari Admin Panel aplikasi.
 */
class InfoPopupResource extends Resource
{
    protected static ?string $model = InfoPopup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $navigationLabel = 'Popup Informasi';

    protected static ?string $modelLabel = 'Popup informasi';

    protected static ?string $pluralModelLabel = 'Popup Informasi';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return InfoPopupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InfoPopupsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInfoPopups::route('/'),
            'create' => CreateInfoPopup::route('/create'),
            'edit' => EditInfoPopup::route('/{record}/edit'),
        ];
    }
}
