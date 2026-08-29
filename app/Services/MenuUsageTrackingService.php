<?php

namespace App\Services;

use App\Models\MenuUsageEvent;
use App\Models\User;
use App\Support\Enums\VisitSource;

class MenuUsageTrackingService
{
    /**
     * Mencatat satu ketukan menu.
     *
     * Setiap ketukan disimpan sebagai baris tersendiri: yang ingin diketahui
     * admin adalah frekuensi pemakaian, bukan jumlah pemakai unik.
     */
    public function record(
        string $menuKey,
        VisitSource $source,
        ?User $user = null,
        ?string $appVersion = null,
        ?string $appBuild = null,
    ): MenuUsageEvent {
        return MenuUsageEvent::query()->create([
            'user_id' => $user?->getKey(),
            'menu_key' => $menuKey,
            'source' => $source->value,
            'occurred_at' => now('UTC'),
            'app_version' => $appVersion,
            'app_build' => $appBuild,
        ]);
    }
}
