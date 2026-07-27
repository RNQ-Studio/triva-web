<?php

namespace App\Services\MarketData;

use App\Exceptions\MarketDataProviderException;
use App\Models\Vehicle;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class OlxHtmlParser
{
    /**
     * Parser intentionally extracts only vehicle attributes required by the
     * appraisal engine. Seller identity, contact data, and listing images are
     * never returned or persisted.
     *
     * @return list<array{
     *     external_reference_hash: string|null,
     *     make: string,
     *     model: string,
     *     variant: string|null,
     *     year: int,
     *     transmission: string|null,
     *     fuel_type: string|null,
     *     mileage: int|null,
     *     listing_price: int,
     *     city: string|null,
     *     observed_at: Carbon,
     *     metadata: array<string, mixed>
     * }>
     */
    public function parse(
        string $html,
        Vehicle $vehicle,
        string $baseUrl,
        ?Carbon $observedAt = null,
    ): array {
        if (! class_exists(DOMDocument::class)) {
            throw new MarketDataProviderException('Ekstensi DOM PHP diperlukan untuk provider HTML.');
        }

        $observedAt ??= now();
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?>'.$html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new MarketDataProviderException('Respons provider tidak dapat diparse.');
        }

        $xpath = new DOMXPath($document);
        $items = [
            ...$this->parseCards($xpath, $vehicle, $baseUrl, $observedAt),
            ...$this->parseJsonLd($xpath, $vehicle, $baseUrl, $observedAt),
        ];

        return collect($items)
            ->unique(fn (array $item): string => $item['external_reference_hash']
                ?? hash('sha256', implode('|', [
                    $item['make'],
                    $item['model'],
                    (string) $item['variant'],
                    (string) $item['year'],
                    (string) $item['listing_price'],
                    (string) $item['mileage'],
                    (string) $item['city'],
                ])))
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     external_reference_hash: string|null,
     *     make: string,
     *     model: string,
     *     variant: string|null,
     *     year: int,
     *     transmission: string|null,
     *     fuel_type: string|null,
     *     mileage: int|null,
     *     listing_price: int,
     *     city: string|null,
     *     observed_at: Carbon,
     *     metadata: array<string, mixed>
     * }>
     */
    private function parseCards(
        DOMXPath $xpath,
        Vehicle $vehicle,
        string $baseUrl,
        Carbon $observedAt,
    ): array {
        $cards = $xpath->query(
            "//*[contains(@data-aut-id, 'itemBox') or contains(@data-testid, 'listing-card')]",
        );
        if ($cards === false) {
            return [];
        }

        $items = [];
        foreach ($cards as $card) {
            if (! $card instanceof DOMElement) {
                continue;
            }

            $title = $this->nodeText($xpath, $card, [
                ".//*[@data-aut-id='itemTitle']",
                ".//*[@data-testid='listing-title']",
                './/h2',
            ]);
            $priceText = $this->nodeText($xpath, $card, [
                ".//*[@data-aut-id='itemPrice']",
                ".//*[@data-testid='listing-price']",
            ]);
            $details = $this->nodeText($xpath, $card, [
                ".//*[@data-aut-id='itemDetails']",
                ".//*[@data-testid='listing-details']",
            ]);
            $location = $this->nodeText($xpath, $card, [
                ".//*[@data-aut-id='item-location']",
                ".//*[@data-testid='listing-location']",
            ]);

            $item = $this->item(
                title: $title,
                price: $this->price($priceText),
                details: $details,
                location: $location,
                externalReference: $this->cardUrl($xpath, $card, $baseUrl),
                vehicle: $vehicle,
                observedAt: $observedAt,
                parser: 'olx_card',
            );
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @return list<array{
     *     external_reference_hash: string|null,
     *     make: string,
     *     model: string,
     *     variant: string|null,
     *     year: int,
     *     transmission: string|null,
     *     fuel_type: string|null,
     *     mileage: int|null,
     *     listing_price: int,
     *     city: string|null,
     *     observed_at: Carbon,
     *     metadata: array<string, mixed>
     * }>
     */
    private function parseJsonLd(
        DOMXPath $xpath,
        Vehicle $vehicle,
        string $baseUrl,
        Carbon $observedAt,
    ): array {
        $scripts = $xpath->query("//script[@type='application/ld+json']");
        if ($scripts === false) {
            return [];
        }

        $products = [];
        foreach ($scripts as $script) {
            $payload = json_decode($script->textContent, true);
            if (! is_array($payload)) {
                continue;
            }
            $this->collectProducts($payload, $products);
        }

        $items = [];
        foreach ($products as $product) {
            $offer = is_array($product['offers'] ?? null) ? $product['offers'] : [];
            $location = data_get($product, 'availableAtOrFrom.address.addressLocality')
                ?? data_get($product, 'itemOffered.availableAtOrFrom.address.addressLocality');
            $item = $this->item(
                title: (string) ($product['name'] ?? ''),
                price: $this->price((string) ($offer['price'] ?? $product['price'] ?? '')),
                details: trim(implode(' ', array_filter([
                    $product['modelDate'] ?? null,
                    filled(data_get($product, 'mileageFromOdometer.value'))
                        ? data_get($product, 'mileageFromOdometer.value').' km'
                        : null,
                    $product['vehicleTransmission'] ?? null,
                    $product['fuelType'] ?? null,
                ], fn (mixed $value): bool => is_scalar($value)))),
                location: is_scalar($location) ? (string) $location : '',
                externalReference: $this->absoluteUrl(
                    (string) ($product['url'] ?? ''),
                    $baseUrl,
                ),
                vehicle: $vehicle,
                observedAt: $observedAt,
                parser: 'json_ld',
            );
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<mixed>  $payload
     * @param  list<array<string, mixed>>  $products
     */
    private function collectProducts(array $payload, array &$products): void
    {
        $type = $payload['@type'] ?? null;
        if (in_array($type, ['Product', 'Vehicle', 'Car'], true)) {
            /** @var array<string, mixed> $payload */
            $products[] = $payload;
        }

        foreach ($payload as $value) {
            if (! is_array($value)) {
                continue;
            }

            if (isset($value['item']) && is_array($value['item'])) {
                $this->collectProducts($value['item'], $products);
            } else {
                $this->collectProducts($value, $products);
            }
        }
    }

    /**
     * @return array{
     *     external_reference_hash: string|null,
     *     make: string,
     *     model: string,
     *     variant: string|null,
     *     year: int,
     *     transmission: string|null,
     *     fuel_type: string|null,
     *     mileage: int|null,
     *     listing_price: int,
     *     city: string|null,
     *     observed_at: Carbon,
     *     metadata: array<string, mixed>
     * }|null
     */
    private function item(
        string $title,
        ?int $price,
        string $details,
        string $location,
        string $externalReference,
        Vehicle $vehicle,
        Carbon $observedAt,
        string $parser,
    ): ?array {
        $searchable = Str::lower($title.' '.$details);
        if (
            $price === null
            || ! str_contains($searchable, Str::lower($vehicle->make))
            || ! str_contains($searchable, Str::lower($vehicle->model))
        ) {
            return null;
        }

        $year = $this->year($title.' '.$details);
        if ($year === null) {
            return null;
        }

        return [
            'external_reference_hash' => filled($externalReference)
                ? hash('sha256', $externalReference)
                : null,
            'make' => $vehicle->make,
            'model' => $vehicle->model,
            'variant' => Str::limit(trim($title), 160, ''),
            'year' => $year,
            'transmission' => $this->transmission($title.' '.$details),
            'fuel_type' => $this->fuelType($title.' '.$details),
            'mileage' => $this->mileage($details),
            'listing_price' => $price,
            'city' => $this->location($location),
            'observed_at' => $observedAt->copy(),
            'metadata' => [
                'parser' => $parser,
                'listing_title' => Str::limit(trim($title), 200, ''),
            ],
        ];
    }

    /** @param list<string> $queries */
    private function nodeText(
        DOMXPath $xpath,
        DOMNode $context,
        array $queries,
    ): string {
        foreach ($queries as $query) {
            $nodes = $xpath->query($query, $context);
            if ($nodes === false) {
                continue;
            }
            $node = $nodes->item(0);
            if ($node !== null && filled(trim($node->textContent))) {
                return trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            }
        }

        return '';
    }

    private function cardUrl(DOMXPath $xpath, DOMNode $card, string $baseUrl): string
    {
        $nodes = $xpath->query('.//a[@href]', $card);
        $node = $nodes === false ? null : $nodes->item(0);

        return $node instanceof DOMElement
            ? $this->absoluteUrl($node->getAttribute('href'), $baseUrl)
            : '';
    }

    private function absoluteUrl(string $url, string $baseUrl): string
    {
        if ($url === '') {
            return '';
        }
        if (str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($url, '/');
    }

    private function price(string $value): ?int
    {
        $digits = preg_replace('/[^\d]/', '', $value);
        if ($digits === null || $digits === '') {
            return null;
        }

        $price = (int) $digits;

        return $price > 0 ? $price : null;
    }

    private function year(string $value): ?int
    {
        if (preg_match('/\b((?:19|20)\d{2})\b/', $value, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function mileage(string $value): ?int
    {
        if (
            preg_match(
                '/([\d.,]+)\s*-\s*([\d.,]+)\s*km\b/ui',
                $value,
                $matches,
            ) === 1
        ) {
            $low = (int) preg_replace('/[^\d]/', '', $matches[1]);
            $high = (int) preg_replace('/[^\d]/', '', $matches[2]);

            return (int) round(($low + $high) / 2);
        }

        if (preg_match('/([\d.,]+)\s*km\b/ui', $value, $matches) !== 1) {
            return null;
        }

        return (int) preg_replace('/[^\d]/', '', $matches[1]);
    }

    private function transmission(string $value): ?string
    {
        return match (true) {
            preg_match('/\b(a\/t|at|matic|automatic|otomatis)\b/ui', $value) === 1 => 'automatic',
            preg_match('/\b(m\/t|mt|manual)\b/ui', $value) === 1 => 'manual',
            default => null,
        };
    }

    private function fuelType(string $value): ?string
    {
        return match (true) {
            preg_match('/\b(hybrid|hev|phev)\b/ui', $value) === 1 => 'hybrid',
            preg_match('/\b(electric|listrik|ev)\b/ui', $value) === 1 => 'electric',
            preg_match('/\b(diesel|solar)\b/ui', $value) === 1 => 'diesel',
            preg_match('/\b(gasoline|bensin)\b/ui', $value) === 1 => 'gasoline',
            default => null,
        };
    }

    private function location(string $value): ?string
    {
        $location = preg_split(
            '/(?=Hari ini|Kemarin|\d+\s+(?:hari|jam|menit|Jul|Agu|Sep|Okt|Nov|Des|Jan|Feb|Mar|Apr|Mei|Jun))/ui',
            trim($value),
        )[0] ?? '';

        return filled($location) ? Str::limit(trim($location), 100, '') : null;
    }
}
