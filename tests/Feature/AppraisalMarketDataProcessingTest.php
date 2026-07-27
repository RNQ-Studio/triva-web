<?php

namespace Tests\Feature;

use App\Jobs\ProcessAppraisalMarketData;
use App\Models\Appraisal;
use App\Models\MarketDataSource;
use App\Models\User;
use App\Services\AppraisalMarketDataService;
use App\Services\AppraisalReviewService;
use App\Support\Enums\AppraisalStatus;
use App\Support\Enums\MarketDataSourceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppraisalMarketDataProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_olx_provider_requires_auditable_permission(): void
    {
        $source = MarketDataSource::query()->where('code', 'olx_approved_html')->firstOrFail();

        $this->expectException(ValidationException::class);
        $source->update(['status' => MarketDataSourceStatus::Active]);
    }

    public function test_it_fetches_olx_data_and_persists_an_automatic_estimate(): void
    {
        $source = MarketDataSource::query()->where('code', 'olx_approved_html')->firstOrFail();
        $source->update([
            'status' => MarketDataSourceStatus::Active,
            'approval_reference' => 'OLX-TRIVA-TEST-2026',
            'approved_at' => now()->subDay(),
            'approval_expires_at' => now()->addYear(),
        ]);
        Http::fake([
            'https://www.olx.co.id/*' => Http::response($this->olxCards(8), 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);
        $appraisal = Appraisal::factory()->create([
            'status' => AppraisalStatus::CollectingMarketData,
            'submitted_at' => now(),
            'tax_status' => 'active',
            'flood_history' => 'no',
            'major_accident_history' => 'no',
            'service_history' => 'complete',
            'ownership' => 'first',
            'condition_percentage' => 90,
        ]);

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(8, $estimate->comparable_count);
        self::assertNotNull($estimate->market_mid);
        self::assertSame(['olx_approved_html'], $estimate->provider_codes);
        self::assertSame(AppraisalStatus::AutoEstimated, $appraisal->refresh()->status);
        $this->assertDatabaseCount('appraisal_market_comparables', 8);
        $this->assertDatabaseHas('market_data_sources', [
            'id' => $source->getKey(),
            'last_error_code' => null,
        ]);
        Http::assertSent(fn ($request): bool => str_starts_with(
            $request->url(),
            'https://www.olx.co.id/mobil-bekas_c198/q-toyota-avanza',
        ));

        $published = app(AppraisalReviewService::class)->publishResult(
            $appraisal->refresh(),
            User::factory()->create(),
            [
                'market_estimate_id' => $estimate->id,
                'market_low' => $estimate->market_low,
                'market_mid' => $estimate->market_mid,
                'market_high' => $estimate->market_high,
                'trade_in_low' => $estimate->trade_in_low,
                'trade_in_high' => $estimate->trade_in_high,
                'data_as_of' => $estimate->data_as_of,
                'valid_until' => now()->addDays(7),
                'requires_physical_inspection' => true,
                'disclaimer' => 'Hasil merupakan indikasi dan belum merupakan penawaran final.',
                'adjustments' => $estimate->adjustments,
            ],
            $estimate->comparables
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
                    'metadata' => ['provenance' => 'market_estimate'],
                ])
                ->values()
                ->all(),
        );
        self::assertSame('approved_engine', $published->publication_type);
        self::assertNull($published->override_reason_code);
        self::assertSame(AppraisalStatus::ResultReady, $appraisal->refresh()->status);
    }

    public function test_job_falls_back_to_manual_review_when_no_approved_provider_is_active(): void
    {
        $appraisal = Appraisal::factory()->create([
            'status' => AppraisalStatus::CollectingMarketData,
            'submitted_at' => now(),
        ]);

        (new ProcessAppraisalMarketData($appraisal->id))->handle(
            app(AppraisalMarketDataService::class),
        );

        self::assertSame(
            AppraisalStatus::InsufficientComparables,
            $appraisal->refresh()->status,
        );
        $this->assertDatabaseHas('appraisal_market_estimates', [
            'appraisal_id' => $appraisal->id,
            'status' => 'failed',
            'failure_code' => 'no_eligible_provider',
            'comparable_count' => 0,
        ]);
    }

    private function olxCards(int $count): string
    {
        return collect(range(1, $count))
            ->map(fn (int $index): string => <<<HTML
            <div data-aut-id="itemBox">
              <a href="/item/avanza-{$index}">
                <span data-aut-id="itemTitle">Toyota Avanza 1.5 G AT 2022</span>
                <span data-aut-id="itemPrice">Rp 19{$index}.000.000</span>
                <span data-aut-id="itemDetails">2022 - 4{$index}.000 km - Bensin</span>
                <span data-aut-id="item-location">SurabayaHari ini</span>
              </a>
            </div>
            HTML)
            ->implode("\n");
    }
}
