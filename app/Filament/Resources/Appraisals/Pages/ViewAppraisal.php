<?php

namespace App\Filament\Resources\Appraisals\Pages;

use App\Filament\Resources\Appraisals\AppraisalResource;
use App\Models\Appraisal;
use App\Models\User;
use App\Services\AppraisalMarketDataService;
use App\Support\Enums\AppraisalStatus;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAppraisal extends ViewRecord
{
    protected static string $resource = AppraisalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshMarketData')
                ->label('Proses ulang otomatis')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->authorize(
                    fn (Appraisal $record): bool => auth()->user()
                        ?->can('manageAutomaticProcessing', $record) ?? false,
                )
                ->hidden(fn (Appraisal $record): bool => ! in_array($record->status, [
                    AppraisalStatus::Submitted,
                    AppraisalStatus::CollectingMarketData,
                    AppraisalStatus::AutoEstimated,
                    AppraisalStatus::InsufficientComparables,
                    AppraisalStatus::UnderAppraiserReview,
                    AppraisalStatus::Failed,
                ], true))
                ->requiresConfirmation()
                ->modalDescription(
                    'TRIVA akan menjalankan ulang scraping OLX, fallback OpenAI, kalkulasi engine, dan publikasi hasil secara otomatis.',
                )
                ->action(function (
                    Appraisal $record,
                    AppraisalMarketDataService $service,
                ): void {
                    /** @var User $user */
                    $user = auth()->user();
                    try {
                        $service->requestRefresh($record, $user);
                        Notification::make()
                            ->title('Pemrosesan otomatis masuk antrean')
                            ->success()
                            ->send();
                        $this->refreshFormData([]);
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Data pasar belum dapat diproses')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
