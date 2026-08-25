<?php

namespace App\Services;

use App\Models\ToyotaSscCampaign;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Pemeriksaan mandiri No. Rangka: keterlibatan SSC dan sisa fasilitas T-Care.
 *
 * Notulensi 19 Agustus 2026 menyebut tujuan bisnisnya secara eksplisit --
 * memfilter pelanggan yang hendak servis ke OtoXpert agar beralih ke Auto2000
 * selama fasilitas T-Care masih berlaku.
 */
class VehicleBenefitLookupService
{
    /**
     * @return array{
     *     vin: string,
     *     year: int|null,
     *     ssc: array<string, mixed>,
     *     t_care: array<string, mixed>,
     *     recommendation: array<string, string>
     * }
     */
    public function check(string $vin, ?int $year): array
    {
        $normalizedVin = $this->normalizeVin($vin);
        $ssc = $this->ssc($normalizedVin, $year);
        $tCare = $this->tCare($year);

        return [
            'vin' => $normalizedVin,
            'year' => $year,
            'ssc' => $ssc,
            't_care' => $tCare,
            'recommendation' => $this->recommendation($ssc, $tCare),
        ];
    }

    /** @return array<string, mixed> */
    private function ssc(string $vin, ?int $year): array
    {
        $campaigns = ToyotaSscCampaign::query()->effective()->get();
        if ($campaigns->isEmpty()) {
            // Belum ada data kampanye dari TAM. Menjawab "tidak terlibat" di
            // sini akan menyesatkan pelanggan, jadi dikatakan apa adanya.
            return [
                'status' => 'unverified',
                'label' => 'Belum dapat dipastikan',
                'message' => 'Data kampanye SSC untuk unit Anda belum tersedia di aplikasi. Hubungi Auto2000 Kertajaya untuk pengecekan resmi berdasarkan nomor rangka.',
                'campaigns' => [],
            ];
        }

        $matched = $campaigns
            ->filter(fn (ToyotaSscCampaign $campaign): bool => $campaign->covers($vin, $year))
            ->values();

        if ($matched->isEmpty()) {
            return [
                'status' => 'not_affected',
                'label' => 'Tidak terlibat SSC',
                'message' => 'Nomor rangka Anda tidak termasuk kampanye SSC yang sedang berjalan.',
                'campaigns' => [],
            ];
        }

        return [
            'status' => 'affected',
            'label' => 'Terlibat SSC',
            'message' => 'Unit Anda termasuk kampanye SSC. Pengerjaannya gratis di bengkel resmi Toyota.',
            'campaigns' => $matched
                ->map(fn (ToyotaSscCampaign $campaign): array => [
                    'campaign_code' => $campaign->campaign_code,
                    'title' => $campaign->title,
                    'description' => $campaign->description,
                    'recommended_action' => $campaign->recommended_action,
                ])
                ->all(),
        ];
    }

    /**
     * T-Care berlaku sejak tahun kendaraan selama masa cakupan yang
     * dikonfigurasi. Perhitungannya sengaja konservatif: tahun kendaraan
     * dianggap mulai berlaku 1 Januari, sehingga sisa masa tidak pernah
     * dilebih-lebihkan.
     *
     * @return array<string, mixed>
     */
    private function tCare(?int $year): array
    {
        $coverageYears = (int) config('toyota.t_care.coverage_years');
        if ($year === null) {
            return [
                'status' => 'unknown',
                'label' => 'Tahun kendaraan belum diisi',
                'coverage_years' => $coverageYears,
                'months_remaining' => null,
                'expires_on' => null,
                'message' => 'Isi tahun kendaraan untuk menghitung sisa fasilitas T-Care.',
            ];
        }

        $expiresOn = Carbon::create($year, 1, 1, 0, 0, 0, 'Asia/Jakarta')
            ->addYears($coverageYears);
        $today = now('Asia/Jakarta')->startOfDay();
        $isActive = $expiresOn->greaterThan($today);
        $monthsRemaining = $isActive
            ? (int) floor($today->diffInMonths($expiresOn))
            : 0;

        return [
            'status' => $isActive ? 'active' : 'expired',
            'label' => $isActive ? 'Masih berlaku' : 'Sudah berakhir',
            'coverage_years' => $coverageYears,
            'months_remaining' => $monthsRemaining,
            'expires_on' => $expiresOn->toDateString(),
            'message' => $isActive
                ? 'Fasilitas T-Care unit Anda masih berlaku sekitar '.$monthsRemaining.' bulan lagi. Manfaatkan servis berkala di Auto2000 Kertajaya selama masih tercakup.'
                : 'Fasilitas T-Care unit Anda sudah berakhir. Servis tetap dapat dilakukan di Auto2000 Kertajaya maupun OtoXpert.',
        ];
    }

    /**
     * @param  array<string, mixed>  $ssc
     * @param  array<string, mixed>  $tCare
     * @return array<string, string>
     */
    private function recommendation(array $ssc, array $tCare): array
    {
        if ($ssc['status'] === 'affected') {
            return [
                'channel' => 'toyota_service',
                'title' => 'Booking servis Toyota',
                'message' => 'Unit Anda perlu pengerjaan kampanye SSC yang hanya dilayani bengkel resmi Toyota.',
            ];
        }

        if ($tCare['status'] === 'active') {
            return [
                'channel' => 'toyota_service',
                'title' => 'Booking servis Toyota',
                'message' => 'Fasilitas T-Care masih berlaku, jadi servis berkala di Auto2000 Kertajaya lebih hemat untuk Anda.',
            ];
        }

        return [
            'channel' => 'otoxpert',
            'title' => 'Booking OtoXpert',
            'message' => 'Tanpa cakupan T-Care aktif, OtoXpert dapat menjadi pilihan servis yang lebih fleksibel.',
        ];
    }

    private function normalizeVin(string $value): string
    {
        return (string) Str::of($value)->replaceMatches('/[^A-Za-z0-9]/', '')->upper();
    }
}
