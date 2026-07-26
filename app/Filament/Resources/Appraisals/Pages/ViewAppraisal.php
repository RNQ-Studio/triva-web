<?php

namespace App\Filament\Resources\Appraisals\Pages;

use App\Exceptions\AppraisalConflictException;
use App\Filament\Resources\Appraisals\AppraisalResource;
use App\Models\Appraisal;
use App\Models\User;
use App\Services\AppraisalReviewService;
use App\Support\Enums\AppraisalPhotoAngle;
use App\Support\Enums\AppraisalStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAppraisal extends ViewRecord
{
    protected static string $resource = AppraisalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('startReview')
                ->label('Mulai review')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->authorize(fn (Appraisal $record): bool => auth()->user()?->can('review', $record) ?? false)
                ->hidden(fn (Appraisal $record): bool => ! in_array($record->status, [
                    AppraisalStatus::Submitted,
                    AppraisalStatus::CollectingMarketData,
                    AppraisalStatus::AutoEstimated,
                    AppraisalStatus::InsufficientComparables,
                ], true))
                ->action(function (Appraisal $record, AppraisalReviewService $service): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $service->startReview($record, $user);
                    Notification::make()->title('Review appraisal dimulai')->success()->send();
                    $this->refreshFormData([]);
                }),
            Action::make('requestPhotoCorrection')
                ->label('Minta foto ulang')
                ->icon('heroicon-o-camera')
                ->color('warning')
                ->authorize(fn (Appraisal $record): bool => auth()->user()?->can('review', $record) ?? false)
                ->hidden(fn (Appraisal $record): bool => ! in_array($record->status, [
                    AppraisalStatus::CollectingMarketData,
                    AppraisalStatus::AutoEstimated,
                    AppraisalStatus::InsufficientComparables,
                    AppraisalStatus::UnderAppraiserReview,
                ], true))
                ->form([
                    Select::make('angle')
                        ->label('Sudut foto')
                        ->options(collect(AppraisalPhotoAngle::cases())->mapWithKeys(
                            fn (AppraisalPhotoAngle $angle): array => [$angle->value => $angle->label()]
                        ))
                        ->required(),
                    Textarea::make('note')
                        ->label('Alasan dan panduan perbaikan')
                        ->required()
                        ->minLength(20)
                        ->maxLength(1000)
                        ->rows(4),
                ])
                ->action(function (Appraisal $record, array $data, AppraisalReviewService $service): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $service->requestPhotoCorrection(
                        $record,
                        $user,
                        AppraisalPhotoAngle::from($data['angle']),
                        $data['note'],
                    );
                    Notification::make()->title('Permintaan foto ulang dikirim')->success()->send();
                    $this->refreshFormData([]);
                }),
            Action::make('publishResult')
                ->label('Terbitkan hasil')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->authorize(fn (Appraisal $record): bool => auth()->user()?->can('review', $record) ?? false)
                ->hidden(fn (Appraisal $record): bool => ! in_array($record->status, [
                    AppraisalStatus::CollectingMarketData,
                    AppraisalStatus::AutoEstimated,
                    AppraisalStatus::InsufficientComparables,
                    AppraisalStatus::UnderAppraiserReview,
                    AppraisalStatus::ResultReady,
                ], true))
                ->form($this->resultForm())
                ->action(function (Appraisal $record, array $data, AppraisalReviewService $service): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $comparables = collect($data['comparables'])
                        ->map(function (array $comparable): array {
                            $externalReference = $comparable['external_reference'] ?? null;
                            unset($comparable['external_reference']);

                            return [
                                ...$comparable,
                                'external_reference_hash' => filled($externalReference)
                                    ? hash('sha256', $externalReference)
                                    : null,
                                'metadata' => ['provenance' => 'filament_manual_entry'],
                            ];
                        })
                        ->all();
                    unset($data['comparables']);

                    try {
                        $service->publishResult($record, $user, $data, $comparables);
                        Notification::make()->title('Hasil appraisal diterbitkan')->success()->send();
                        $this->refreshFormData([]);
                    } catch (AppraisalConflictException $exception) {
                        Notification::make()
                            ->title('Hasil belum dapat diterbitkan')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    /** @return array<int, mixed> */
    private function resultForm(): array
    {
        return [
            TextInput::make('market_low')->label('Pasar terendah')->numeric()->prefix('Rp')->required()->minValue(1),
            TextInput::make('market_mid')->label('Median pasar')->numeric()->prefix('Rp')->required()->minValue(1),
            TextInput::make('market_high')->label('Pasar tertinggi')->numeric()->prefix('Rp')->required()->minValue(1),
            TextInput::make('trade_in_low')->label('Trade-in terendah')->numeric()->prefix('Rp')->required()->minValue(1),
            TextInput::make('trade_in_high')->label('Trade-in tertinggi')->numeric()->prefix('Rp')->required()->minValue(1),
            DateTimePicker::make('data_as_of')->label('Data per')->default(now())->required()->seconds(false),
            DateTimePicker::make('valid_until')->label('Berlaku hingga')->default(now()->addDays(7))->required()->after('data_as_of')->seconds(false),
            Toggle::make('requires_physical_inspection')->label('Wajib inspeksi fisik')->default(true),
            Textarea::make('disclaimer')
                ->label('Disclaimer pelanggan')
                ->default('Hasil merupakan indikasi dan belum merupakan penawaran final.')
                ->required()
                ->maxLength(2000)
                ->columnSpanFull(),
            Repeater::make('comparables')
                ->label('Data pembanding dan provenance')
                ->minItems(1)
                ->defaultItems(1)
                ->addActionLabel('Tambah pembanding')
                ->schema([
                    Select::make('source_code')
                        ->label('Sumber')
                        ->options([
                            'manual_appraiser' => 'Input appraiser',
                            'approved_csv' => 'CSV disetujui',
                            'partner_feed' => 'Feed partner',
                        ])
                        ->required(),
                    TextInput::make('external_reference')
                        ->label('Referensi eksternal')
                        ->helperText('Nilai asli di-hash; data penjual tidak disimpan.')
                        ->maxLength(500),
                    TextInput::make('make')->label('Merek')->required()->maxLength(80),
                    TextInput::make('model')->label('Model')->required()->maxLength(100),
                    TextInput::make('variant')->label('Varian')->maxLength(120),
                    TextInput::make('year')->label('Tahun')->numeric()->required()->minValue(1950)->maxValue(now()->year + 1),
                    TextInput::make('mileage')->label('Kilometer')->numeric()->minValue(0),
                    TextInput::make('listing_price')->label('Harga listing')->numeric()->prefix('Rp')->required()->minValue(1),
                    TextInput::make('city')->label('Kota')->maxLength(100),
                    DateTimePicker::make('observed_at')->label('Diamati pada')->default(now())->required()->seconds(false),
                    TextInput::make('similarity_score')->label('Similarity 0–1')->numeric()->default(0.8)->required()->minValue(0)->maxValue(1),
                    Toggle::make('is_outlier')->label('Outlier')->default(false),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }
}
