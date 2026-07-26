<?php

namespace Tests\Feature\Api;

use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class RegionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_load_indonesian_provinces_and_cities(): void
    {
        $indonesia = $this->region(type: 'country', code: 'ID', name: 'Indonesia');
        $otherCountry = $this->region(type: 'country', code: 'MY', name: 'Malaysia');
        $eastJava = $this->region(
            type: 'state',
            code: '35',
            name: 'JAWA TIMUR',
            parentId: $indonesia->getKey(),
        );
        $aceh = $this->region(
            type: 'state',
            code: '11',
            name: 'ACEH',
            parentId: $indonesia->getKey(),
        );
        $otherState = $this->region(
            type: 'state',
            code: 'MY-01',
            name: 'JOHOR',
            parentId: $otherCountry->getKey(),
        );
        $this->region(
            type: 'city',
            code: '3578',
            name: 'KOTA SURABAYA',
            parentId: $eastJava->getKey(),
        );
        $this->region(
            type: 'city',
            code: '3573',
            name: 'KOTA MALANG',
            parentId: $eastJava->getKey(),
        );
        $this->region(
            type: 'city',
            code: '1101',
            name: 'KABUPATEN SIMEULUE',
            parentId: $aceh->getKey(),
        );
        $this->region(
            type: 'city',
            code: 'MY-0101',
            name: 'JOHOR BAHRU',
            parentId: $otherState->getKey(),
        );

        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/regions/provinces')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'ACEH')
            ->assertJsonPath('data.0.cities.0.name', 'KABUPATEN SIMEULUE')
            ->assertJsonPath('data.1.name', 'JAWA TIMUR')
            ->assertJsonPath('data.1.cities.0.name', 'KOTA MALANG')
            ->assertJsonPath('data.1.cities.1.name', 'KOTA SURABAYA')
            ->assertJsonMissing(['name' => 'JOHOR']);
    }

    public function test_region_master_requires_authentication(): void
    {
        $this->getJson('/api/v1/regions/provinces')->assertUnauthorized();
    }

    public function test_region_master_returns_empty_list_when_data_is_unavailable(): void
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson('/api/v1/regions/provinces')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Master wilayah belum tersedia.')
            ->assertJsonCount(0, 'data');
    }

    private function region(
        string $type,
        string $code,
        string $name,
        ?int $parentId = null,
    ): Region {
        return Region::query()->create([
            'parent_id' => $parentId,
            'type' => $type,
            'code' => $code,
            'name' => $name,
        ]);
    }
}
