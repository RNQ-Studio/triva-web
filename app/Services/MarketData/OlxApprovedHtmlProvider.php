<?php

namespace App\Services\MarketData;

use App\Contracts\MarketDataProvider;
use App\Exceptions\MarketDataProviderException;
use App\Models\Appraisal;
use App\Models\MarketDataSource;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OlxApprovedHtmlProvider implements MarketDataProvider
{
    public function __construct(
        private readonly OlxHtmlParser $parser,
        private readonly RateLimiter $limiter,
    ) {}

    public function code(): string
    {
        return 'olx_approved_html';
    }

    public function fetch(Appraisal $appraisal, MarketDataSource $source): array
    {
        if ($source->code !== $this->code() || ! $source->isEligible()) {
            throw new MarketDataProviderException('Provider OLX belum memenuhi governance izin.');
        }

        $appraisal->loadMissing('vehicle');
        $url = $this->searchUrl($source, implode(' ', [
            $appraisal->vehicle->make,
            $appraisal->vehicle->model,
            $appraisal->vehicle->variant,
            (string) $appraisal->vehicle->year,
        ]));
        $settings = $source->settings ?? [];
        $maxPages = max(1, min(3, (int) ($settings['max_pages'] ?? 1)));
        $items = [];
        for ($page = 1; $page <= $maxPages; $page++) {
            $pageUrl = $page === 1 ? $url : $url.'?page='.$page;
            $html = $this->request($pageUrl, $source);
            $pageItems = $this->parser->parse(
                $html,
                $appraisal->vehicle,
                $source->base_url,
            );
            if ($page > 1 && $pageItems === []) {
                break;
            }
            $items = [...$items, ...$pageItems];
        }

        return collect($items)
            ->unique(fn (array $item): string => $item['external_reference_hash']
                ?? hash('sha256', json_encode($item, JSON_THROW_ON_ERROR)))
            ->map(fn (array $item): array => [
                'market_data_source_id' => $source->getKey(),
                'source_code' => $source->code,
                ...$item,
            ])
            ->values()
            ->all();
    }

    private function request(string $url, MarketDataSource $source): string
    {
        $key = 'market-data-provider:'.$source->code;
        if ($this->limiter->tooManyAttempts($key, $source->rate_limit_per_minute)) {
            throw new MarketDataProviderException('Rate limit provider sedang penuh.');
        }
        $this->limiter->hit($key, 60);
        try {
            return Http::accept('text/html')
                ->withHeaders(['Accept-Language' => 'id-ID,id;q=0.9'])
                ->withUserAgent((string) config('appraisal.market_data.user_agent'))
                ->timeout((int) config('appraisal.market_data.timeout_seconds'))
                ->get($url)
                ->throw()
                ->body();
        } catch (ConnectionException|RequestException $exception) {
            throw new MarketDataProviderException(
                'Provider OLX mengembalikan respons HTTP yang tidak berhasil.',
                previous: $exception,
            );
        }
    }

    private function searchUrl(MarketDataSource $source, string $query): string
    {
        $parts = parse_url($source->base_url);
        $host = Str::lower((string) ($parts['host'] ?? ''));
        $allowedHosts = collect(config('appraisal.market_data.allowed_hosts', []))
            ->map(fn (mixed $allowed): string => Str::lower((string) $allowed));

        if (
            ($parts['scheme'] ?? null) !== 'https'
            || $host === ''
            || ! $allowedHosts->contains($host)
        ) {
            throw new MarketDataProviderException('Host provider tidak termasuk allowlist.');
        }

        $settings = $source->settings ?? [];
        $path = (string) ($settings['search_path'] ?? '/mobil-bekas_c198/q-{query}');
        if (! str_starts_with($path, '/') || str_contains($path, '://')) {
            throw new MarketDataProviderException('Template path provider tidak valid.');
        }

        return rtrim($source->base_url, '/').str_replace(
            '{query}',
            rawurlencode(Str::slug($query)),
            $path,
        );
    }
}
