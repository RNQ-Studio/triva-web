<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DECISION_REFERENCE = 'TRIVA-PRODUCT-DECISION-2026-07-30';

    public function up(): void
    {
        DB::table('market_data_sources')
            ->where('code', 'openai_market_research')
            ->where('status', 'active')
            ->update([
                'status' => 'suspended',
                'updated_at' => now(),
            ]);

        DB::table('market_data_sources')->updateOrInsert(
            ['code' => 'openai_price_decision'],
            [
                'name' => 'OpenAI — keputusan harga otomatis',
                'type' => 'ai_price_decision',
                'status' => 'active',
                'base_url' => 'https://api.openai.com',
                'approval_reference' => self::DECISION_REFERENCE,
                'approved_at' => now(),
                'approval_expires_at' => now()->addYear(),
                'rate_limit_per_minute' => 6,
                'retention_days' => 30,
                'settings' => json_encode([
                    'maximum_partial_olx_evidence' => 5,
                    'maximum_confidence' => 'medium',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('market_data_sources')
            ->where('code', 'openai_price_decision')
            ->delete();

        DB::table('market_data_sources')
            ->where('code', 'openai_market_research')
            ->where('status', 'suspended')
            ->update([
                'status' => 'active',
                'updated_at' => now(),
            ]);
    }
};
