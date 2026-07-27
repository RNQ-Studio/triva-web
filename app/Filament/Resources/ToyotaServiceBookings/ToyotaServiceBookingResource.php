<?php

namespace App\Filament\Resources\ToyotaServiceBookings;

use App\Filament\Resources\ToyotaServiceBookings\Pages\ListToyotaServiceBookings;
use App\Filament\Resources\ToyotaServiceBookings\Pages\ScheduleToyotaServiceBookings;
use App\Filament\Resources\ToyotaServiceBookings\Pages\ViewToyotaServiceBooking;
use App\Filament\Resources\ToyotaServiceBookings\Schemas\ToyotaServiceBookingInfolist;
use App\Filament\Resources\ToyotaServiceBookings\Tables\ToyotaServiceBookingsTable;
use App\Models\ToyotaServiceBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ToyotaServiceBookingResource extends Resource
{
    protected static ?string $model = ToyotaServiceBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Toyota Service';

    protected static ?string $navigationLabel = 'Booking Queue';

    protected static ?string $modelLabel = 'booking Toyota';

    protected static ?string $pluralModelLabel = 'booking Toyota';

    protected static ?string $recordTitleAttribute = 'reference_no';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('service_bookings.viewAny') ?? false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return ToyotaServiceBookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToyotaServiceBookingsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'user',
            'vehicle.vehicleMake',
            'vehicle.vehicleModel',
            'serviceLocation',
            'serviceType',
            'assignedServiceAdvisor',
            'photos.asset',
            'benefitChecks.verifiedBy',
            'statusHistories.changedBy',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToyotaServiceBookings::route('/'),
            'schedule' => ScheduleToyotaServiceBookings::route('/schedule'),
            'view' => ViewToyotaServiceBooking::route('/{record}'),
        ];
    }
}
