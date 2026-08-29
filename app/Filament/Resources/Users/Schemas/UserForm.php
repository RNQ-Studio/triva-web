<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Asset;
use App\Services\AssetDeletionService;
use App\Services\AssetUploadService;
use App\Services\FileUploadService;
use App\Support\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar')
                    ->image()
                    ->imageEditor()
                    ->maxSize(2048)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->nullable()
                    ->fetchFileInformation(false)
                    ->getUploadedFileUsing(function ($file) {
                        if (! $file) {
                            return null;
                        }
                        if (Str::isUuid($file)) {
                            $asset = Asset::find($file);
                            if ($asset) {
                                return [
                                    'name' => $asset->original_filename,
                                    'size' => $asset->size,
                                    'type' => $asset->mime_type,
                                    'url' => $asset->getPublicUrl(),
                                ];
                            }
                        }
                        // Legacy fallback
                        $disk = Storage::disk(config('filesystems.default', 'public'));
                        if ($disk->exists($file)) {
                            return [
                                'name' => basename($file),
                                'size' => $disk->size($file),
                                'type' => $disk->mimeType($file),
                                'url' => $disk->url($file),
                            ];
                        }

                        return null;
                    })
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, $record) {
                        $asset = app(AssetUploadService::class)->upload(
                            file: $file,
                            type: 'avatar',
                            userId: $record?->getKey(),
                        );

                        if ($record && $record->avatar) {
                            $oldAvatar = $record->avatar;
                            if (Str::isUuid($oldAvatar)) {
                                $oldAsset = Asset::find($oldAvatar);
                                if ($oldAsset) {
                                    app(AssetDeletionService::class)->hardDelete($oldAsset);
                                }
                            } else {
                                app(FileUploadService::class)->delete($oldAvatar);
                            }
                        }

                        return $asset->id;
                    })
                    ->deleteUploadedFileUsing(function ($state) {
                        if (! $state) {
                            return;
                        }
                        if (Str::isUuid($state)) {
                            $asset = Asset::find($state);
                            if ($asset) {
                                app(AssetDeletionService::class)->hardDelete($asset);
                            }
                        } else {
                            app(FileUploadService::class)->delete($state);
                        }
                    }),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Leave blank to keep the current password.')
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(20),
                TextInput::make('city')
                    ->label('Kota/Kabupaten')
                    ->maxLength(100),
                Select::make('gender')
                    ->label('Gender')
                    ->options(Gender::options())
                    ->native(false),
                DatePicker::make('birth_date')
                    ->label('Tanggal lahir')
                    ->maxDate(now()->subYears(17))
                    ->minDate(now()->subYears(100))
                    ->displayFormat('d M Y'),
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }
}
