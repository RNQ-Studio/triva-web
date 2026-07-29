<?php

namespace App\Jobs;

use App\Exceptions\NoEligibleMarketDataSourceException;
use App\Models\Appraisal;
use App\Services\AppraisalMarketDataService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessAppraisalMarketData implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 170;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly string $appraisalId,
        public readonly bool $force = false,
    ) {}

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('appraisal-market:'.$this->appraisalId))
                ->releaseAfter(30)
                ->expireAfter(210),
        ];
    }

    public function handle(AppraisalMarketDataService $marketData): void
    {
        $appraisal = Appraisal::query()->findOrFail($this->appraisalId);
        try {
            $marketData->process($appraisal, $this->force);
        } catch (NoEligibleMarketDataSourceException) {
            $marketData->markProcessingFailed(
                $appraisal,
                'no_eligible_provider',
                'Provider OLX dan fallback OpenAI belum aktif.',
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $appraisal = Appraisal::query()->find($this->appraisalId);
        if ($appraisal === null) {
            return;
        }

        app(AppraisalMarketDataService::class)->markProcessingFailed(
            $appraisal,
            'provider_unavailable',
            'OLX dan fallback OpenAI gagal setelah seluruh percobaan.',
        );
    }
}
