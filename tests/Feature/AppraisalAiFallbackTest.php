<?php

namespace Tests\Feature;

use App\Models\Appraisal;
use App\Models\AppraisalAiAgentRun;
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
            'appraisal.ai.research_model' => 'gpt-5.6-sol',
            'appraisal.ai.review_model' => 'gpt-5.6-sol',
        ]);
    }

    public function test_two_agents_supply_grounded_comparables_when_olx_has_no_results(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_market_research');
        $candidates = $this->candidates(8);
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->researchResponse($candidates), 200)
                ->push($this->reviewResponse($candidates), 200),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Ready, $estimate->status);
        self::assertSame(8, $estimate->comparable_count);
        self::assertSame(
            ['olx_approved_html', 'openai_market_research'],
            $estimate->provider_codes,
        );
        self::assertSame(AppraisalStatus::AutoEstimated, $appraisal->refresh()->status);
        $this->assertDatabaseCount('appraisal_ai_agent_runs', 2);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'research',
            'status' => 'completed',
            'candidate_count' => 8,
        ]);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'review',
            'status' => 'completed',
            'accepted_count' => 8,
        ]);
        $this->assertDatabaseHas('appraisal_market_comparables', [
            'source_code' => 'openai_market_research',
            'listing_price' => 191_000_000,
        ]);
        $storedAudit = json_encode(
            AppraisalAiAgentRun::query()->pluck('output')->all(),
            JSON_THROW_ON_ERROR,
        );
        self::assertStringNotContainsString('081234567890', $storedAudit);
        self::assertStringNotContainsString('seller@example.com', $storedAudit);

        $openAiRequests = collect(Http::recorded())
            ->filter(fn (array $record): bool => $record[0]->url()
                === 'https://api.openai.com/v1/responses')
            ->values();
        self::assertCount(2, $openAiRequests);
        foreach ($openAiRequests as [$request]) {
            self::assertSame('gpt-5.6-sol', $request['model']);
            self::assertFalse($request['store']);
            self::assertSame(
                ['www.olx.co.id', 'olx.co.id'],
                $request['tools'][0]['filters']['allowed_domains'],
            );
            self::assertSame('json_schema', $request['text']['format']['type']);
            self::assertTrue($request['text']['format']['strict']);
        }
    }

    public function test_ai_agents_are_not_called_when_olx_already_has_enough_data(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_market_research');
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
        $this->assertDatabaseCount('appraisal_ai_agent_runs', 0);
        Http::assertNotSent(fn ($request): bool => str_starts_with(
            $request->url(),
            'https://api.openai.com/',
        ));
    }

    public function test_candidate_without_a_consulted_source_is_never_used_even_if_ai_accepts_it(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_market_research');
        $candidate = $this->candidates(1);
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->researchResponse(
                    $candidate,
                    ['https://www.olx.co.id/item/sumber-yang-berbeda'],
                ), 200)
                ->push($this->reviewResponse($candidate), 200),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Insufficient, $estimate->status);
        self::assertSame(0, $estimate->comparable_count);
        self::assertSame(
            AppraisalStatus::InsufficientComparables,
            $appraisal->refresh()->status,
        );
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'research',
            'candidate_count' => 0,
        ]);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'review',
            'accepted_count' => 0,
        ]);
        $this->assertDatabaseCount('appraisal_market_comparables', 0);
    }

    public function test_research_candidates_are_not_used_when_the_reviewer_rejects_them(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_market_research');
        $candidates = $this->candidates(8);
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push($this->researchResponse($candidates), 200)
                ->push($this->reviewResponse($candidates, false), 200),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Insufficient, $estimate->status);
        self::assertSame(0, $estimate->comparable_count);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'research',
            'candidate_count' => 8,
        ]);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'review',
            'candidate_count' => 8,
            'accepted_count' => 0,
        ]);
        $this->assertDatabaseCount('appraisal_market_comparables', 0);
    }

    public function test_missing_openai_configuration_keeps_the_appraisal_in_manual_review(): void
    {
        $this->activateSource('olx_approved_html');
        $this->activateSource('openai_market_research');
        config(['appraisal.ai.openai.api_key' => null]);
        Http::fake([
            'https://www.olx.co.id/*' => Http::response(
                '<html><body>Tidak ada hasil</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);
        $appraisal = $this->appraisal();

        $estimate = app(AppraisalMarketDataService::class)->process($appraisal);

        self::assertSame(AppraisalMarketEstimateStatus::Insufficient, $estimate->status);
        self::assertSame(
            AppraisalStatus::InsufficientComparables,
            $appraisal->refresh()->status,
        );
        $this->assertDatabaseHas('market_data_sources', [
            'code' => 'openai_market_research',
            'last_error_code' => 'openai_not_configured',
        ]);
        $this->assertDatabaseHas('appraisal_ai_agent_runs', [
            'appraisal_id' => $appraisal->id,
            'phase' => 'research',
            'status' => 'failed',
            'error_code' => 'openai_not_configured',
        ]);
        Http::assertNotSent(fn ($request): bool => str_starts_with(
            $request->url(),
            'https://api.openai.com/',
        ));
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
            'condition_percentage' => 90,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function candidates(int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index): array => [
                'candidate_id' => 'candidate-'.$index,
                'source_url' => 'https://www.olx.co.id/item/avanza-'.$index,
                'source_title' => 'Toyota Avanza 1.5 G AT 2022',
                'make' => 'Toyota',
                'model' => 'Avanza',
                'variant' => '1.5 G',
                'year' => 2022,
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
                'mileage' => 40_000 + ($index * 1000),
                'listing_price' => 190_000_000 + ($index * 1_000_000),
                'city' => 'Surabaya',
                'evidence_notes' => $index === 1
                    ? 'Harga terlihat. Hubungi 081234567890 atau seller@example.com.'
                    : 'Harga dan spesifikasi terlihat pada listing.',
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @param  list<string>|null  $sourceUrls
     * @return array<string, mixed>
     */
    private function researchResponse(
        array $candidates,
        ?array $sourceUrls = null,
    ): array {
        $sourceUrls ??= collect($candidates)->pluck('source_url')->all();

        return [
            'id' => 'resp_research_123',
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'web_search_call',
                    'status' => 'completed',
                    'action' => [
                        'type' => 'search',
                        'sources' => collect($sourceUrls)
                            ->map(fn (string $url): array => [
                                'type' => 'url',
                                'url' => $url,
                                'title' => 'Listing OLX',
                            ])
                            ->all(),
                    ],
                ],
                [
                    'type' => 'message',
                    'status' => 'completed',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode([
                            'summary' => 'Kandidat ditemukan dari sumber terizinkan.',
                            'candidates' => $candidates,
                        ], JSON_THROW_ON_ERROR),
                        'annotations' => [],
                    ]],
                ],
            ],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 200,
                'total_tokens' => 300,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    private function reviewResponse(array $candidates, bool $accepted = true): array
    {
        return [
            'id' => 'resp_review_123',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'status' => 'completed',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'summary' => 'Kandidat konsisten dengan kendaraan target.',
                        'verdicts' => collect($candidates)
                            ->map(fn (array $candidate): array => [
                                'candidate_id' => $candidate['candidate_id'],
                                'accepted' => $accepted,
                                'confidence' => $accepted ? 'medium' : 'low',
                                'rejection_reason' => $accepted
                                    ? null
                                    : 'Bukti varian tidak cukup kuat.',
                            ])
                            ->all(),
                    ], JSON_THROW_ON_ERROR),
                    'annotations' => [],
                ]],
            ]],
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 100,
                'total_tokens' => 200,
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
