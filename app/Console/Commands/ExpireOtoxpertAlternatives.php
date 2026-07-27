<?php

namespace App\Console\Commands;

use App\Services\OtoxpertAlternativeExpiryService;
use Illuminate\Console\Command;

class ExpireOtoxpertAlternatives extends Command
{
    protected $signature = 'otoxpert:expire-alternatives {--limit=200}';

    protected $description = 'Expire overdue OtoXpert alternative schedules';

    public function handle(OtoxpertAlternativeExpiryService $service): int
    {
        $count = $service->expireDue(max(1, (int) $this->option('limit')));
        $this->info("Reconciled {$count} OtoXpert booking(s).");

        return self::SUCCESS;
    }
}
