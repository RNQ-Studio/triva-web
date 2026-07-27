<?php

namespace App\Jobs;

use App\Exceptions\NoEligibleMarketDataSourceException;
use App\Models\Appraisal;
use App\Services\AppraisalMarketDataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessAppraisalMarketData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $appraisalId,
        public readonly bool $force = false,
    ) {}

    public function handle(AppraisalMarketDataService $marketData): void
    {
        $appraisal = Appraisal::query()->findOrFail($this->appraisalId);
        try {
            $marketData->process($appraisal, $this->force);
        } catch (NoEligibleMarketDataSourceException) {
            $marketData->markForManualReview(
                $appraisal,
                'no_eligible_provider',
                'Tidak ada provider berizin dan aktif.',
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $appraisal = Appraisal::query()->find($this->appraisalId);
        if ($appraisal === null) {
            return;
        }

        app(AppraisalMarketDataService::class)->markForManualReview(
            $appraisal,
            'provider_unavailable',
            'Provider market data gagal setelah seluruh percobaan.',
        );
    }
}
