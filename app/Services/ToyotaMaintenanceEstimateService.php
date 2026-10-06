<?php

namespace App\Services;

use App\Models\ToyotaServicePackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Simulasi biaya servis berkala yang diminta notulensi 19 Agustus 2026.
 *
 * Paket dipilih dari kelipatan kilometer berikutnya yang akan dicapai
 * pelanggan, memakai data paket reguler yang diinput cabang.
 */
class ToyotaMaintenanceEstimateService
{
    /**
     * @return array{
     *     vehicle_model: string|null,
     *     mileage: int|null,
     *     recommended: array<string, mixed>|null,
     *     packages: list<array<string, mixed>>,
     *     available_models: list<string>
     * }
     */
    public function estimate(?string $vehicleModel, ?int $mileage): array
    {
        $vehicleModel = $vehicleModel === null ? null : trim($vehicleModel);
        if ($vehicleModel === '') {
            $vehicleModel = null;
        }

        $packages = $this->packagesFor($vehicleModel);

        return [
            'vehicle_model' => $vehicleModel,
            'mileage' => $mileage,
            'recommended' => $this->recommended($packages, $mileage),
            'packages' => $packages
                ->map(fn (ToyotaServicePackage $package): array => $this->present($package))
                ->values()
                ->all(),
            'available_models' => $this->availableModels(),
        ];
    }

    /**
     * Model yang punya paket khusus, sebagai saran isian model di aplikasi
     * supaya nama yang diketik pelanggan cocok dengan tabel budget admin.
     *
     * @return list<string>
     */
    private function availableModels(): array
    {
        return ToyotaServicePackage::query()
            ->effective()
            ->whereNotNull('vehicle_model')
            ->distinct()
            ->pluck('vehicle_model')
            ->map(fn (mixed $model): string => trim((string) $model))
            ->filter(fn (string $model): bool => $model !== '')
            // Urutan byte mendahulukan huruf kapital, jadi dari varian
            // "Raize"/"raize" yang dipertahankan adalah tulisan rapinya.
            ->sort(fn (string $a, string $b): int => strcmp($a, $b))
            ->unique(fn (string $model): string => Str::lower($model))
            ->sort(fn (string $a, string $b): int => strnatcasecmp($a, $b))
            ->values()
            ->all();
    }

    /**
     * Paket khusus model didahulukan; paket umum hanya melengkapi kelipatan
     * kilometer yang belum punya versi khusus.
     *
     * @return Collection<int, ToyotaServicePackage>
     */
    private function packagesFor(?string $vehicleModel): Collection
    {
        $packages = ToyotaServicePackage::query()
            ->effective()
            ->where(function ($query) use ($vehicleModel): void {
                $query->whereNull('vehicle_model');
                if (filled($vehicleModel)) {
                    $query->orWhereRaw(
                        'lower(vehicle_model) = ?',
                        [Str::lower($vehicleModel)],
                    );
                }
            })
            ->orderBy('km_interval')
            ->get();

        return $packages
            ->groupBy('km_interval')
            ->map(fn (Collection $group): ToyotaServicePackage => $group
                ->sortByDesc(fn (ToyotaServicePackage $package): int => $package->vehicle_model === null ? 0 : 1)
                ->first())
            ->sortKeys()
            ->values();
    }

    /**
     * @param  Collection<int, ToyotaServicePackage>  $packages
     * @return array<string, mixed>|null
     */
    private function recommended(Collection $packages, ?int $mileage): ?array
    {
        if ($packages->isEmpty()) {
            return null;
        }

        if ($mileage === null) {
            return $this->present($packages->first());
        }

        // Kelipatan berikutnya yang belum dilewati; bila kilometernya sudah
        // melampaui seluruh paket, yang terbesar dipakai sebagai acuan.
        $next = $packages->first(
            fn (ToyotaServicePackage $package): bool => $package->km_interval >= $mileage,
        ) ?? $packages->last();

        return $this->present($next);
    }

    /** @return array<string, mixed> */
    private function present(ToyotaServicePackage $package): array
    {
        return [
            'id' => $package->getKey(),
            'code' => $package->code,
            'name' => $package->name,
            'description' => $package->description,
            'vehicle_model' => $package->vehicle_model,
            'km_interval' => $package->km_interval,
            'parts_cost' => $package->parts_cost,
            'labor_cost' => $package->labor_cost,
            'total_cost' => $package->totalCost(),
            'currency' => 'IDR',
            'includes' => $package->includes ?? [],
            'duration_min_minutes' => $package->duration_min_minutes,
            'duration_max_minutes' => $package->duration_max_minutes,
            'source_reference' => $package->source_reference,
            'is_estimate' => true,
            'disclaimer' => 'Perkiraan biaya paket reguler. Biaya final mengikuti pemeriksaan Service Advisor di bengkel.',
        ];
    }
}
