<?php

namespace Tests\Unit\Services\Credit;

use App\Services\Credit\AccCreditCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccCreditCalculatorTest extends TestCase
{
    public function test_it_reproduces_the_branch_worksheet_example(): void
    {
        // Lembar kerja Simulasi_ACC: OTR 183.260.000, DP 52.596.000, tenor 5
        // tahun, bunga 5,80%, asuransi 5,36% -> angsuran 3.020.000, TDP
        // 57.016.000.
        $quote = app(AccCreditCalculator::class)->quoteWithRates(
            otrPrice: 183_260_000,
            downPayment: 52_596_000,
            tenorYears: 5,
            interestRateBps: 580,
            insuranceRateBps: 536,
        );

        self::assertSame(130_664_000, $quote['principal']);
        self::assertSame(9_823_000, $quote['insurance_premium']);
        self::assertSame(140_487_000, $quote['financed_amount']);
        self::assertSame(40_741_230, $quote['total_flat_interest']);
        self::assertSame(3_020_000, $quote['monthly_installment']);
        self::assertSame(57_016_000, $quote['total_down_payment']);
        self::assertSame(60, $quote['tenor_months']);
    }

    public function test_quote_looks_up_rate_by_class_dp_tier_tenor_and_otr_band(): void
    {
        $calculator = app(AccCreditCalculator::class);

        $quote = $calculator->quote(183_260_000, 30, 5, 'Veloz Hybrid');

        self::assertSame('reg2', $quote['vehicle_class']);
        self::assertSame(900, $quote['annual_flat_rate_basis_points']);
        self::assertSame(536, $quote['insurance_rate_basis_points']);
        self::assertSame(54_978_000, $quote['down_payment']);
        self::assertSame(30, $quote['dp_percent']);
        self::assertSame(
            $quote['down_payment'] + $quote['monthly_installment'] + 1_000_000 + 400_000,
            $quote['total_down_payment'],
        );

        // DP 20% memakai kolom 25% pada lembar kerja.
        self::assertSame(
            970,
            $calculator->quote(183_260_000, 20, 5, 'Veloz Hybrid')['annual_flat_rate_basis_points'],
        );
        self::assertSame('reg1', $calculator->vehicleClass('Raize'));
        self::assertSame('agya', $calculator->vehicleClass('Agya GR Sport'));
        self::assertSame('lux', $calculator->vehicleClass('Alphard'));
        self::assertSame('reg2', $calculator->vehicleClass('Model tidak dikenal'));
        self::assertSame(591, $calculator->insuranceRateBps(100_000_000, 5));
        self::assertSame(404, $calculator->insuranceRateBps(2_000_000_000, 5));
        self::assertSame(278, $calculator->insuranceRateBps(183_260_000, 1));
    }

    public function test_unsupported_dp_or_tenor_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(AccCreditCalculator::class)->quote(183_260_000, 15, 5, 'Veloz');
    }
}
