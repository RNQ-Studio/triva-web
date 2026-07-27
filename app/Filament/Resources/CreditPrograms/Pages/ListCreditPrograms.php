<?php

namespace App\Filament\Resources\CreditPrograms\Pages;

use App\Filament\Resources\CreditPrograms\CreditProgramResource;
use App\Models\User;
use App\Services\CreditProgramCsvImportService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListCreditPrograms extends ListRecords
{
    protected static string $resource = CreditProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('import_csv')
                ->label('Preview / import CSV')
                ->authorize(
                    fn (): bool => auth()->user()
                        ?->can('credit_programs.create') ?? false
                )
                ->form([
                    FileUpload::make('file')
                        ->label('File CSV')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                        ])
                        ->storeFiles(false)
                        ->helperText(
                            'Gunakan header template program kredit. Preview tidak menyimpan perubahan.'
                        )
                        ->required(),
                    Toggle::make('import_now')
                        ->label('Import setelah preview valid')
                        ->default(false),
                ])
                ->action(function (
                    array $data,
                    CreditProgramCsvImportService $importer,
                ): void {
                    $file = $data['file'] ?? null;
                    if (! $file instanceof TemporaryUploadedFile) {
                        Notification::make()
                            ->title('File CSV tidak valid')
                            ->danger()
                            ->send();

                        return;
                    }
                    $preview = $importer->preview($file->getRealPath());
                    if ($preview['errors'] !== []) {
                        Notification::make()
                            ->title('Preview menemukan kesalahan')
                            ->body(implode("\n", array_slice(
                                $preview['errors'],
                                0,
                                8,
                            )))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }
                    if (! ($data['import_now'] ?? false)) {
                        Notification::make()
                            ->title('Preview CSV valid')
                            ->body(
                                "{$preview['program_count']} program dan {$preview['tenor_count']} tenor siap diimpor."
                            )
                            ->success()
                            ->send();

                        return;
                    }
                    /** @var User $actor */
                    $actor = auth()->user();
                    $result = $importer->import(
                        $file->getRealPath(),
                        $actor,
                    );
                    Notification::make()
                        ->title('Program kredit berhasil diimpor')
                        ->body(
                            "{$result['program_count']} program dan {$result['tenor_count']} tenor diproses."
                        )
                        ->success()
                        ->send();
                }),
        ];
    }
}
