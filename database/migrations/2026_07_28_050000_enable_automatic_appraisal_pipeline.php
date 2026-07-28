<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DECISION_REFERENCE = 'TRIVA-PRODUCT-DECISION-2026-07-28';

    public function up(): void
    {
        Schema::table('appraisal_results', function (Blueprint $table): void {
            $table->unsignedBigInteger('published_by')->nullable()->change();
        });

        DB::table('market_data_sources')
            ->whereIn('code', ['olx_approved_html', 'openai_market_research'])
            ->where('status', 'draft')
            ->update([
                'status' => 'active',
                'approval_reference' => self::DECISION_REFERENCE,
                'approved_at' => now(),
                'approval_expires_at' => now()->addYear(),
                'updated_at' => now(),
            ]);

        DB::table('appraisal_status_histories')
            ->where('status', 'insufficient_comparables')
            ->update([
                'title' => 'Pemrosesan otomatis belum berhasil',
                'description' => 'OLX dan fallback OpenAI belum menghasilkan data pembanding yang memadai.',
                'updated_at' => now(),
            ]);

        DB::table('appraisals')
            ->whereIn('status', [
                'insufficient_comparables',
                'under_appraiser_review',
            ])
            ->update([
                'status' => 'failed',
                'assigned_appraiser_id' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (DB::table('appraisal_results')->whereNull('published_by')->exists()) {
            throw new RuntimeException(
                'Rollback ditolak karena hasil appraisal otomatis sudah diterbitkan.',
            );
        }

        DB::table('market_data_sources')
            ->whereIn('code', ['olx_approved_html', 'openai_market_research'])
            ->where('approval_reference', self::DECISION_REFERENCE)
            ->update([
                'status' => 'draft',
                'approval_reference' => null,
                'approved_at' => null,
                'approval_expires_at' => null,
                'updated_at' => now(),
            ]);

        Schema::table('appraisal_results', function (Blueprint $table): void {
            $table->unsignedBigInteger('published_by')->nullable(false)->change();
        });
    }
};
