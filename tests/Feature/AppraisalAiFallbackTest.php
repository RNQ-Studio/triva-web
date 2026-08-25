<?php

namespace Tests\Feature;

use App\Exceptions\MarketDataProviderException;
use App\Models\Appraisal;
use App\Models\MarketDataSource;
use App\Services\AppraisalMarketDataService;
use App\Support\Enums\AppraisalMarketEstimateStatus;
use App\Support\Enums\AppraisalStatus;
use App\Support\Enums\MarketDataSourceStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppraisalAiFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'appraisal.ai.enabled' => true,
            'appraisal.ai.openai.api_key' => 'test-openai-key',
            'appraisal.ai.price_decision_model' => 'gpt-5.6-sol',
        ]);
    }

    public function test_openai_decides_price_from_submitted_specs_when_olx_is_empty(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_price_decision');
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://api.openai.com/v1/responses' => Http::response(
                $this->priceDecisionResponse(),
                200,
            ),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Ready, $estimate->status);
        self::assertSame(0, $estimate->comparable_count);
        self::assertSame(195_000_000, $estimate->market_mid);
        self::assertSame('low', $estimate->confidence->value);
        self::assertSame(
            ['olx_approved_html', 'openai_price_decision'],
            $estimate->provider_codes,
        );
        self::assertSame(
            'openai_price_decision_with_deterministic_trade_in_v1',
            data_get($estimate->calculation, 'algorithm'),
        );
        self::assertSame(AppraisalStatus::ResultReady, $appraisal->refresh()->status);
        $this->assertDatabaseHas('appraisal_results', [
            'appraisal_id' => $appraisal->id,
            'publication_type' => 'automatic_engine',
            'comparable_count' => 0,
            'published_by' => null,
        ]);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'price_decision',
            'status' => 'completed',
            'candidate_count' => 0,
            'accepted_count' => 1,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $appraisal->user_id,
            'type' => 'appraisal_result_ready',
            'title' => 'Hasil appraisal tersedia',
        ]);

        $request = collect(Http::recorded())
            ->first(fn (array $record): bool => $record[0]->url()
                === 'https://api.openai.com/v1/responses')[0];
        self::assertSame('gpt-5.6-sol', $request['model']);
        self::assertFalse($request['store']);
        self::assertArrayNotHasKey('tools', $request->data());
        self::assertSame('json_schema', $request['text']['format']['type']);
        self::assertTrue($request['text']['format']['strict']);
        $input = json_decode($request['input'][1]['content'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Toyota', data_get($input, 'vehicle.make'));
        self::assertSame('Avanza', data_get($input, 'vehicle.model'));
        self::assertSame('1.5 G', data_get($input, 'vehicle.variant'));
        self::assertNull(data_get($input, 'condition.condition_percentage'));
        self::assertSame('b', data_get($input, 'condition.condition_grade'));
    }

    public function test_partial_olx_evidence_is_passed_without_urls_or_seller_data(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_price_decision');
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                $this->olxCards(2),
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://api.openai.com/v1/responses' => Http::response(
                $this->priceDecisionResponse(confidence: 'medium'),
                200,
            ),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Ready, $estimate->status);
        self::assertSame(2, $estimate->comparable_count);
        self::assertSame('medium', $estimate->confidence->value);
        $this->assertDatabaseCount('appraisal_comparables', 2);

        $request = collect(Http::recorded())
            ->first(fn (array $record): bool => $record[0]->url()
                === 'https://api.openai.com/v1/responses')[0];
        $serialized = $request['input'][1]['content'];
        self::assertStringNotContainsString('https://', $serialized);
        self::assertStringNotContainsString('081234567890', $serialized);
        self::assertCount(
            2,
            json_decode($serialized, true, flags: JSON_THROW_ON_ERROR)['partial_olx_evidence'],
        );
    }

    public function test_ai_is_not_called_when_olx_already_has_enough_data(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_price_decision');
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                $this->olxCards(8),
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://api.openai.com/*' => Http::response([], 500),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Ready, $estimate->status);
        self::assertSame(['olx_approved_html'], $estimate->provider_codes);
        self::assertSame(AppraisalStatus::ResultReady, $appraisal->refresh()->status);
        $this->assertDatabaseCount('appraisal_ai_agent_runs', 0);
        Http::assertNotSent(fn ($request): bool => str_starts_with(
            $request->url(),
            'https://api.openai.com/',
        ));
    }

    public function test_invalid_ai_price_range_is_rejected_and_completion_failure_is_notified(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_price_decision');
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
            ),
            'https://api.openai.com/v1/responses' => Http::response(
                $this->priceDecisionResponse(
                    low: 210_000_000,
                    mid: 190_000_000,
                    high: 180_000_000,
                ),
                200,
            ),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Insufficient, $estimate->status);
        self::assertSame(AppraisalStatus::Failed, $appraisal->refresh()->status);
        $this->assertDatabaseMissing('appraisal_results', [
            'appraisal_id' => $appraisal->id,
        ]);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'price_decision',
            'status' => 'failed',
            'error_code' => 'openai_invalid_price_decision',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $appraisal->user_id,
            'type' => 'appraisal_processing_failed',
        ]);
    }

    public function test_transient_openai_failure_bubbles_up_for_queue_retry(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_price_decision');
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
            ),
            'https://api.openai.com/v1/responses' => Http::response([], 503),
        ]);
        $appraisal = $this->appraisal();

        try {
            app(AppraisalMarketDataService::class)->process($appraisal);
            self::fail('Transient OpenAI failure should be retried by the queue.');
        } catch (MarketDataProviderException $exception) {
            self::assertSame(
                'Provider appraisal otomatis gagal sementara.',
                $exception->getMessage(),
            );
        }

        self::assertSame(
            AppraisalStatus::CollectingMarketData,
            $appraisal->refresh()->status,
        );
        $this->assertDatabaseCount('appraisal_market_estimates', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'status' => 'failed',
            'error_code' => 'openai_server_error',
        ]);
    }

    public function test_missing_openai_configuration_marks_processing_failed(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_price_decision');
        config(['appraisal.ai.openai.api_key' => null]);
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
            ),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Insufficient, $estimate->status);
        self::assertSame(AppraisalStatus::Failed, $appraisal->refresh()->status);
        $this->assertDatabaseHas('market_data_sources', [
            'code' => 'openai_price_decision',
            'last_error_code' => 'openai_not_configured',
        ]);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'price_decision',
            'status' => 'failed',
            'error_code' => 'openai_not_configured',
        ]);
    }

    private function activateSource(string $code): MarketDataSource
    {
        $source = MarketDataSource::query()->where('code', $code)->firstOrFail();
        $source->update([
            'status' => MarketDataSourceStatus::Active,
            'approval_reference' => strtoupper($code).'-TEST-2026',
            'approved_at' => now()->subDay(),
            'approval_expires_at' => now()->addYear(),
            'rate_limit_per_minute' => 60,
        ]);

        return $source;
    }

    private function appraisal(): Appraisal
    {
        return Appraisal::factory()->create([
            'status' => AppraisalStatus::CollectingMarketData,
            'submitted_at' => now(),
            'tax_status' => 'active',
            'flood_history' => 'no',
            'major_accident_history' => 'no',
            'service_history' => 'complete',
            'ownership' => 'first',
            'condition_grade' => 'b',
        ]);
    }

    /** @return array<string, mixed> */
    private function priceDecisionResponse(
        int $low = 185_000_000,
        int $mid = 195_000_000,
        int $high = 205_000_000,
        string $confidence = 'medium',
    ): array {
        return [
            'id' => 'resp_price_decision_123',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'market_low' => $low,
                        'market_mid' => $mid,
                        'market_high' => $high,
                        'confidence' => $confidence,
                        'rationale' => 'Rentang konservatif untuk spesifikasi target.',
                        'assumptions' => [
                            'Dokumen dan identitas kendaraan sesuai input.',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    'annotations' => [],
                ]],
            ]],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 80,
                'total_tokens' => 180,
            ],
        ];
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
