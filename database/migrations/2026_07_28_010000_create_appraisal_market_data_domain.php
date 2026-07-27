<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('market_data_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->string('type', 40);
            $table->string('status', 30)->default('draft');
            $table->string('base_url', 500);
            $table->string('approval_reference', 255)->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('approval_expires_at')->nullable();
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(6);
            $table->unsignedSmallInteger('retention_days')->default(90);
            $table->jsonb('settings')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'type']);
            $table->index('approval_expires_at');
        });

        Schema::create('appraisal_market_estimates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appraisal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status', 30);
            $table->unsignedBigInteger('market_low')->nullable();
            $table->unsignedBigInteger('market_mid')->nullable();
            $table->unsignedBigInteger('market_high')->nullable();
            $table->unsignedBigInteger('trade_in_low')->nullable();
            $table->unsignedBigInteger('trade_in_high')->nullable();
            $table->string('confidence', 20);
            $table->unsignedSmallInteger('comparable_count')->default(0);
            $table->timestampTz('data_as_of')->nullable();
            $table->jsonb('provider_codes')->nullable();
            $table->jsonb('adjustments')->nullable();
            $table->jsonb('calculation')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message')->nullable();
            $table->timestampTz('calculated_at');
            $table->timestampsTz();

            $table->unique(['appraisal_id', 'version']);
            $table->index(['appraisal_id', 'calculated_at']);
            $table->index(['status', 'calculated_at']);
        });

        Schema::create('appraisal_market_comparables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appraisal_market_estimate_id')
                ->constrained('appraisal_market_estimates')
                ->cascadeOnDelete();
            $table->foreignId('market_data_source_id')
                ->nullable()
                ->constrained('market_data_sources')
                ->nullOnDelete();
            $table->string('source_code', 60);
            $table->char('external_reference_hash', 64)->nullable();
            $table->char('deduplication_hash', 64);
            $table->string('make', 80);
            $table->string('model', 100);
            $table->string('variant', 160)->nullable();
            $table->unsignedSmallInteger('year');
            $table->string('transmission', 30)->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->unsignedInteger('mileage')->nullable();
            $table->unsignedBigInteger('listing_price');
            $table->string('city', 100)->nullable();
            $table->timestampTz('observed_at');
            $table->decimal('similarity_score', 5, 4)->default(0);
            $table->decimal('weight', 8, 4)->default(0);
            $table->boolean('is_duplicate')->default(false);
            $table->boolean('is_outlier')->default(false);
            $table->string('exclusion_reason', 100)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['appraisal_market_estimate_id', 'is_outlier']);
            $table->index(['source_code', 'observed_at']);
            $table->index('deduplication_hash');
        });

        Schema::table('appraisal_results', function (Blueprint $table): void {
            $table->foreignUuid('market_estimate_id')
                ->nullable()
                ->after('appraisal_id')
                ->constrained('appraisal_market_estimates')
                ->nullOnDelete();
            $table->string('publication_type', 30)
                ->default('manual')
                ->after('adjustments');
            $table->string('override_reason_code', 60)
                ->nullable()
                ->after('publication_type');
            $table->text('override_notes')
                ->nullable()
                ->after('override_reason_code');
        });

        DB::table('market_data_sources')->insert([
            'code' => 'olx_approved_html',
            'name' => 'OLX Indonesia — HTML berizin',
            'type' => 'approved_html',
            'status' => 'draft',
            'base_url' => 'https://www.olx.co.id',
            'rate_limit_per_minute' => 6,
            'retention_days' => 90,
            'settings' => json_encode([
                'search_path' => '/mobil-bekas_c198/q-{query}',
                'max_pages' => 1,
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('appraisal_results', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('market_estimate_id');
            $table->dropColumn([
                'publication_type',
                'override_reason_code',
                'override_notes',
            ]);
        });

        Schema::dropIfExists('appraisal_market_comparables');
        Schema::dropIfExists('appraisal_market_estimates');
        Schema::dropIfExists('market_data_sources');
    }
};
