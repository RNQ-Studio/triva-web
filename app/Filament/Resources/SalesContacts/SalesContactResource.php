<?php

namespace App\Filament\Resources\SalesContacts;

use App\Filament\Resources\SalesContacts\Pages\CreateSalesContact;
use App\Filament\Resources\SalesContacts\Pages\EditSalesContact;
use App\Filament\Resources\SalesContacts\Pages\ListSalesContacts;
use App\Filament\Resources\SalesContacts\Schemas\SalesContactForm;
use App\Filament\Resources\SalesContacts\Tables\SalesContactsTable;
use App\Models\SalesContact;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Manajemen data sales dan supervisor yang ditawarkan aplikasi sebagai kontak
 * WhatsApp (revisi 4 September 2026).
 */
class SalesContactResource extends Resource
{
    protected static ?string $model = SalesContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $navigationLabel = 'Data Sales';

    protected static ?string $modelLabel = 'Sales';

    protected static ?string $pluralModelLabel = 'Data Sales';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SalesContactForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesContactsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalesContacts::route('/'),
            'create' => CreateSalesContact::route('/create'),
            'edit' => EditSalesContact::route('/{record}/edit'),
        ];
    }
}
