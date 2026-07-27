<?php

namespace App\Filament\Resources\BodyPaintPriceItems\Pages;

use App\Filament\Resources\BodyPaintPriceItems\BodyPaintPriceItemResource;
use App\Models\User;
use App\Services\BodyPaintPriceMatrixCsvImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListBodyPaintPriceItems extends ListRecords
{
    protected static string $resource = BodyPaintPriceItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_csv')
                ->label('Preview / import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->authorize(
                    fn (): bool => auth()->user()
                        ?->can('bp_price_matrix.create') ?? false,
                )
                ->form([
                    FileUpload::make('file')
                        ->label('File CSV price matrix')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                        ])
                        ->storeFiles(false)
                        ->helperText(
                            'Preview memvalidasi seluruh baris. Import bersifat all-or-nothing dan versi yang sudah ada immutable.',
                        )
                        ->required(),
                    Toggle::make('import_now')
                        ->label('Import setelah preview valid')
                        ->default(false),
                ])
                ->action(function (
                    array $data,
                    BodyPaintPriceMatrixCsvImportService $importer,
                ): void {
                    $file = $data['file'] ?? null;
                    if (! $file instanceof TemporaryUploadedFile) {
                        Notification::make()
                            ->title('File CSV tidak valid')
                            ->danger()
                            ->send();

                        return;
                    }

                    /** @var User $actor */
                    $actor = auth()->user();

                    try {
                        $result = $importer->process(
                            $file,
                            $actor,
                            (bool) ($data['import_now'] ?? false),
                        );
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title('CSV belum dapat diimpor')
                            ->body(collect($exception->errors())->flatten()->first())
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    if ($result['error_count'] > 0) {
                        $errors = collect($result['rows'])
                            ->where('status', 'error')
                            ->take(8)
                            ->map(fn (array $row): string => 'Baris '.$row['line'].': '.implode('; ', $row['errors']))
                            ->implode("\n");
                        Notification::make()
                            ->title("Preview: {$result['error_count']} baris bermasalah")
                            ->body($errors)
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title(($data['import_now'] ?? false)
                            ? 'Price matrix berhasil diimpor'
                            : 'Preview CSV valid')
                        ->body(($data['import_now'] ?? false)
                            ? "{$result['imported_count']} item baru disimpan."
                            : "{$result['valid_count']} item siap diimpor.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
