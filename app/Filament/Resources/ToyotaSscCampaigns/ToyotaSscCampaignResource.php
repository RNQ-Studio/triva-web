<?php

namespace App\Filament\Resources\ToyotaSscCampaigns;

use App\Filament\Resources\ToyotaSscCampaigns\Pages\CreateToyotaSscCampaign;
use App\Filament\Resources\ToyotaSscCampaigns\Pages\EditToyotaSscCampaign;
use App\Filament\Resources\ToyotaSscCampaigns\Pages\ListToyotaSscCampaigns;
use App\Filament\Resources\ToyotaSscCampaigns\Schemas\ToyotaSscCampaignForm;
use App\Filament\Resources\ToyotaSscCampaigns\Tables\ToyotaSscCampaignsTable;
use App\Models\ToyotaSscCampaign;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tempat cabang memasukkan kampanye SSC dari TAM, yang menjadi dasar
 * pemeriksaan mandiri No. Rangka di aplikasi pelanggan.
 */
class ToyotaSscCampaignResource extends Resource
{
    protected static ?string $model = ToyotaSscCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Kampanye SSC';

    protected static ?string $modelLabel = 'Kampanye SSC';

    protected static ?string $pluralModelLabel = 'Kampanye SSC';

    protected static ?string $recordTitleAttribute = 'campaign_code';

    public static function form(Schema $schema): Schema
    {
        return ToyotaSscCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToyotaSscCampaignsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToyotaSscCampaigns::route('/'),
            'create' => CreateToyotaSscCampaign::route('/create'),
            'edit' => EditToyotaSscCampaign::route('/{record}/edit'),
        ];
    }
}
