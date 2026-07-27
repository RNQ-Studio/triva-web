<?php

namespace Tests\Unit\Services;

use App\Models\Vehicle;
use App\Services\MarketData\OlxHtmlParser;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class OlxHtmlParserTest extends TestCase
{
    public function test_it_extracts_vehicle_attributes_without_seller_data_or_images(): void
    {
        $html = <<<'HTML'
        <html>
          <body>
            <div data-aut-id="itemBox">
              <a href="/item/toyota-avanza-g-2022-iid-123">
                <img src="https://images.example/seller-car.jpg">
                <span data-aut-id="itemTitle">Toyota Avanza 1.5 G AT 2022</span>
                <span data-aut-id="itemPrice">Rp 195.000.000</span>
                <span data-aut-id="itemDetails">2022 - 40.000-45.000 km - Bensin</span>
                <span data-aut-id="item-location">GubengHari ini</span>
                <span>Hubungi Budi 081234567890</span>
              </a>
            </div>
            <div data-aut-id="itemBox">
              <a href="/item/honda-brio-2022-iid-456">
                <span data-aut-id="itemTitle">Honda Brio 2022</span>
                <span data-aut-id="itemPrice">Rp 150.000.000</span>
                <span data-aut-id="itemDetails">2022 - 20.000 km</span>
              </a>
            </div>
          </body>
        </html>
        HTML;
        $vehicle = new Vehicle([
            'make' => 'Toyota',
            'model' => 'Avanza',
            'variant' => '1.5 G',
            'year' => 2022,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'mileage' => 42_500,
            'city' => 'Surabaya',
        ]);

        $items = (new OlxHtmlParser)->parse(
            $html,
            $vehicle,
            'https://www.olx.co.id',
            Carbon::parse('2026-07-28T01:00:00Z'),
        );

        self::assertCount(1, $items);
        self::assertSame(195_000_000, $items[0]['listing_price']);
        self::assertSame(42_500, $items[0]['mileage']);
        self::assertSame('automatic', $items[0]['transmission']);
        self::assertSame('gasoline', $items[0]['fuel_type']);
        self::assertSame('Gubeng', $items[0]['city']);
        self::assertSame('olx_card', $items[0]['metadata']['parser']);
        self::assertArrayNotHasKey('seller', $items[0]);
        self::assertStringNotContainsString(
            '081234567890',
            json_encode($items[0], JSON_THROW_ON_ERROR),
        );
        self::assertStringNotContainsString(
            'seller-car.jpg',
            json_encode($items[0], JSON_THROW_ON_ERROR),
        );
    }

    public function test_it_supports_json_ld_vehicle_products(): void
    {
        $html = <<<'HTML'
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "ItemList",
          "itemListElement": [{
            "@type": "ListItem",
            "item": {
              "@type": "Product",
              "name": "Toyota Avanza 1.5 G Automatic 2021",
              "url": "/item/avanza-2021-iid-9",
              "modelDate": "2021",
              "vehicleTransmission": "Automatic",
              "fuelType": "Gasoline",
              "mileageFromOdometer": {"value": "55000"},
              "offers": {"price": "185000000"}
            }
          }]
        }
        </script>
        HTML;
        $vehicle = new Vehicle([
            'make' => 'Toyota',
            'model' => 'Avanza',
            'variant' => '1.5 G',
            'year' => 2022,
            'transmission' => 'automatic',
            'fuel_type' => 'gasoline',
            'mileage' => 42_500,
            'city' => 'Surabaya',
        ]);

        $items = (new OlxHtmlParser)->parse($html, $vehicle, 'https://www.olx.co.id');

        self::assertCount(1, $items);
        self::assertSame(2021, $items[0]['year']);
        self::assertSame(55_000, $items[0]['mileage']);
        self::assertSame(185_000_000, $items[0]['listing_price']);
        self::assertSame('json_ld', $items[0]['metadata']['parser']);
    }
}
