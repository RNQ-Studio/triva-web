<?php

namespace Tests\Feature;

use App\Jobs\ProcessAppraisalMarketData;
use App\Models\Appraisal;
use App\Models\MarketDataSource;
use App\Services\AppraisalMarketDataService;
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
        $source->update([
            'status' => MarketDataSourceStatus::Draft,
            'approval_reference' => null,
            'approved_at' => null,
            'approval_expires_at' => null,
        ]);

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
        self::assertSame(AppraisalStatus::ResultReady, $appraisal->refresh()->status);
        $this->assertDatabaseCount('appraisal_market_comparables', 8);
        $this->assertDatabaseCount('appraisal_comparables', 8);
        $this->assertDatabaseHas('market_data_sources', [
            'id' => $source->getKey(),
            'last_error_code' => null,
        ]);
        Http::assertSent(fn ($request): bool => str_starts_with(
            $request->url(),
            'https://www.olx.co.id/mobil-bekas_c198/q-toyota-avanza',
        ));

        $published = $appraisal->results()->firstOrFail();
        self::assertSame('automatic_engine', $published->publication_type);
        self::assertNull($published->override_reason_code);
        self::assertNull($published->published_by);
        self::assertSame(AppraisalStatus::ResultReady, $appraisal->refresh()->status);

        app(AppraisalMarketDataService::class)->process($appraisal->refresh());
        $this->assertDatabaseCount('appraisal_results', 1);
        self::assertSame(
            1,
            $appraisal->statusHistories()
                ->where('status', AppraisalStatus::ResultReady->value)
                ->count(),
        );
    }

    public function test_job_marks_automatic_processing_failed_when_no_provider_is_active(): void
    {
        MarketDataSource::query()
            ->whereIn('code', ['olx_approved_html', 'openai_market_research'])
            ->get()
            ->each(fn (MarketDataSource $source) => $source->update([
                'status' => MarketDataSourceStatus::Draft,
            ]));
        $appraisal = Appraisal::factory()->create([
            'status' => AppraisalStatus::CollectingMarketData,
            'submitted_at' => now(),
        ]);

        (new ProcessAppraisalMarketData($appraisal->id))->handle(
            app(AppraisalMarketDataService::class),
        );

        self::assertSame(
            AppraisalStatus::Failed,
            $appraisal->refresh()->status,
        );
        $this->assertDatabaseHas('appraisal_market_estimates', [
            'appraisal_id' => $appraisal->id,
            'status' => 'failed',
            'failure_code' => 'no_eligible_provider',
            'comparable_count' => 0,
        ]);
        $this->assertDatabaseHas('appraisal_status_histories', [
            'appraisal_id' => $appraisal->id,
            'status' => AppraisalStatus::Failed->value,
            'title' => 'Pemrosesan otomatis belum berhasil',
        ]);
    }

    public function test_job_releases_an_insufficient_estimate_for_automatic_retry(): void
    {
        config(['appraisal.ai.enabled' => false]);
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                $this->olxCards(2),
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);
        $appraisal = Appraisal::factory()->create([
            'status' => AppraisalStatus::CollectingMarketData,
            'submitted_at' => now(),
        ]);
        $job = (new ProcessAppraisalMarketData($appraisal->id, true))
            ->withFakeQueueInteractions();

        $job->handle(app(AppraisalMarketDataService::class));

        $job->assertReleased(30);
        self::assertSame(AppraisalStatus::Failed, $appraisal->refresh()->status);
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
