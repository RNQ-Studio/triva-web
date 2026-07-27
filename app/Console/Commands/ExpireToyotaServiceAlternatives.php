<?php

namespace App\Console\Commands;

use App\Services\ToyotaServiceAlternativeExpiryService;
use Illuminate\Console\Command;

class ExpireToyotaServiceAlternatives extends Command
{
    protected $signature = 'toyota-service:expire-alternatives';

    protected $description = 'Expire unanswered Toyota service alternative schedules safely';

    public function handle(ToyotaServiceAlternativeExpiryService $service): int
    {
        $count = $service->expireDue();
        $this->info("Reconciled {$count} expired Toyota service alternative(s).");

        return self::SUCCESS;
    }
}
