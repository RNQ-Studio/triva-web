<?php

namespace App\Services;

use App\Models\VisitEvent;
use App\Support\Enums\VisitSource;

class VisitTrackingService
{
    public function record(
        VisitSource $source,
        string $externalVisitId,
        ?string $appVersion = null,
        ?string $appBuild = null,
    ): VisitEvent {
        return VisitEvent::query()->firstOrCreate(
            [
                'source' => $source->value,
                'visit_key' => $this->visitKey($source, $externalVisitId),
            ],
            [
                'occurred_at' => now('UTC'),
                'app_version' => $appVersion,
                'app_build' => $appBuild,
            ],
        );
    }

    private function visitKey(VisitSource $source, string $externalVisitId): string
    {
        return hash_hmac(
            'sha256',
            $source->value.'|'.$externalVisitId,
            (string) config('app.key'),
        );
    }
}
