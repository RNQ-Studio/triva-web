<?php

namespace App\Filament\Resources\Appraisals\Pages;

use App\Exceptions\AppraisalConflictException;
use App\Filament\Resources\Appraisals\AppraisalResource;
use App\Models\Appraisal;
use App\Models\AppraisalMarketEstimate;
use App\Models\User;
use App\Services\AppraisalMarketDataService;
use App\Services\AppraisalReviewService;
use App\Support\Enums\AppraisalPhotoAngle;
use App\Support\Enums\AppraisalStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
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
            Action::make('refreshMarketData')
                ->label('Proses ulang data pasar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->authorize(fn (Appraisal $record): bool => auth()->user()?->can('review', $record) ?? false)
                ->hidden(fn (Appraisal $record): bool => ! in_array($record->status, [
                    AppraisalStatus::Submitted,
                    AppraisalStatus::CollectingMarketData,
                    AppraisalStatus::AutoEstimated,
                    AppraisalStatus::InsufficientComparables,
                    AppraisalStatus::UnderAppraiserReview,
                ], true))
                ->requiresConfirmation()
                ->modalDescription(
                    'TRIVA akan mengambil ulang data dari provider berizin dan membuat versi rekomendasi engine baru.',
                )
                ->action(function (Appraisal $record, AppraisalMarketDataService $service): void {
                    /** @var User $user */
                    $user = auth()->user();
                    try {
                        $service->requestRefresh($record, $user);
                        Notification::make()
                            ->title('Pemrosesan data pasar masuk antrean')
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
                ->fillForm(fn (Appraisal $record): array => $this->resultDefaults($record))
                ->action(function (Appraisal $record, array $data, AppraisalReviewService $service): void {
                    /** @var User $user */
                    $user = auth()->user();
                    $marketEstimateId = $data['market_estimate_id'] ?? null;
                    $comparables = collect($data['comparables'])
                        ->map(function (array $comparable) use ($marketEstimateId): array {
                            $externalReference = $comparable['external_reference'] ?? null;
                            $externalReferenceHash = $comparable['external_reference_hash'] ?? null;
                            unset(
                                $comparable['external_reference'],
                                $comparable['external_reference_hash'],
                            );

                            return [
                                ...$comparable,
                                'external_reference_hash' => filled($externalReferenceHash)
                                    ? $externalReferenceHash
                                    : (filled($externalReference)
                                        ? hash('sha256', $externalReference)
                                        : null),
                                'metadata' => [
                                    'provenance' => filled($marketEstimateId)
                                        ? 'appraisal_market_estimate'
                                        : 'filament_manual_entry',
                                    'market_estimate_id' => $marketEstimateId,
                                ],
                            ];
                        })
                        ->all();
                    unset($data['comparables']);
                    $estimate = filled($marketEstimateId)
                        ? AppraisalMarketEstimate::query()->find($marketEstimateId)
                        : null;
                    $data['adjustments'] = $estimate instanceof AppraisalMarketEstimate
                        ? ($estimate->adjustments ?? [])
                        : [];

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
            Hidden::make('market_estimate_id'),
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
            Select::make('override_reason_code')
                ->label('Alasan override/manual')
                ->options([
                    'condition_review' => 'Koreksi setelah review kondisi',
                    'variant_mismatch' => 'Varian pembanding tidak setara',
                    'market_volatility' => 'Pergerakan pasar tidak stabil',
                    'insufficient_comparables' => 'Pembanding tidak mencukupi',
                    'manual_assessment' => 'Penilaian manual appraiser',
                    'other' => 'Lainnya',
                ])
                ->helperText('Boleh kosong bila seluruh angka rekomendasi engine diterbitkan tanpa perubahan.'),
            Textarea::make('override_notes')
                ->label('Catatan override/manual')
                ->helperText('Wajib minimal 20 karakter bila angka diubah atau hasil dibuat manual.')
                ->maxLength(2000)
                ->rows(3),
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
                            'olx_approved_html' => 'OLX HTML (izin tertulis)',
                        ])
                        ->required(),
                    Hidden::make('external_reference_hash'),
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

    /** @return array<string, mixed> */
    private function resultDefaults(Appraisal $record): array
    {
        $estimate = $record->latestMarketEstimate()
            ->with('comparables')
            ->first();
        if (
            $estimate === null
            || $estimate->market_low === null
            || $estimate->market_mid === null
            || $estimate->market_high === null
            || $estimate->trade_in_low === null
            || $estimate->trade_in_high === null
        ) {
            return [
                'data_as_of' => now(),
                'valid_until' => now()->addDays(
                    (int) config('appraisal.market_data.result_valid_days'),
                ),
                'requires_physical_inspection' => true,
                'disclaimer' => 'Hasil merupakan indikasi dan belum merupakan penawaran final.',
            ];
        }

        return [
            'market_estimate_id' => $estimate->getKey(),
            'market_low' => $estimate->market_low,
            'market_mid' => $estimate->market_mid,
            'market_high' => $estimate->market_high,
            'trade_in_low' => $estimate->trade_in_low,
            'trade_in_high' => $estimate->trade_in_high,
            'data_as_of' => $estimate->data_as_of ?? now(),
            'valid_until' => now()->addDays(
                (int) config('appraisal.market_data.result_valid_days'),
            ),
            'requires_physical_inspection' => true,
            'disclaimer' => 'Hasil merupakan indikasi berdasarkan data listing pasar dan belum merupakan penawaran final. Nilai final memerlukan inspeksi fisik.',
            'comparables' => $estimate->comparables
                ->whereNull('exclusion_reason')
                ->map(fn ($comparable): array => [
                    'source_code' => $comparable->source_code,
                    'external_reference_hash' => $comparable->external_reference_hash,
                    'make' => $comparable->make,
                    'model' => $comparable->model,
                    'variant' => $comparable->variant,
                    'year' => $comparable->year,
                    'mileage' => $comparable->mileage,
                    'listing_price' => $comparable->listing_price,
                    'city' => $comparable->city,
                    'observed_at' => $comparable->observed_at,
                    'similarity_score' => $comparable->similarity_score,
                    'is_outlier' => false,
                ])
                ->values()
                ->all(),
        ];
    }
}
