<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('make', 80);
            $table->string('model', 100);
            $table->string('variant', 120);
            $table->unsignedSmallInteger('year');
            $table->string('transmission', 30);
            $table->string('fuel_type', 30);
            $table->unsignedInteger('mileage');
            $table->string('color', 60);
            $table->string('license_plate', 20);
            $table->string('city', 100);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['user_id', 'updated_at']);
            $table->index(['make', 'model', 'variant', 'year']);
        });

        Schema::create('appraisals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('reference_no', 40)->unique();
            $table->string('status', 40)->default('draft');
            $table->uuid('idempotency_key')->nullable();

            $table->string('tax_status', 30)->nullable();
            $table->string('flood_history', 30)->nullable();
            $table->string('major_accident_history', 30)->nullable();
            $table->string('service_history', 30)->nullable();
            $table->string('ownership', 30)->nullable();

            $table->timestampTz('service_consent_at')->nullable();
            $table->boolean('marketing_consent')->default(false);
            $table->foreignId('assigned_appraiser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->string('customer_decision', 30)->nullable();
            $table->timestampTz('customer_decided_at')->nullable();
            $table->timestampTz('inspection_scheduled_at')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['status', 'due_at']);
            $table->index(['assigned_appraiser_id', 'status']);
        });

        Schema::create('appraisal_photos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->restrictOnDelete();
            $table->string('angle', 40);
            $table->unsignedSmallInteger('version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->string('review_status', 30)->default('pending');
            $table->text('rejection_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->unique('asset_id');
            $table->unique(['appraisal_id', 'angle', 'version']);
            $table->index(['appraisal_id', 'is_current']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX appraisal_photos_current_angle_unique
             ON appraisal_photos (appraisal_id, angle)
             WHERE is_current = true'
        );

        Schema::create('appraisal_status_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appraisal_id')->constrained()->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->boolean('user_visible')->default(true);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->index(['appraisal_id', 'created_at']);
        });

        Schema::create('appraisal_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appraisal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->unsignedBigInteger('market_low');
            $table->unsignedBigInteger('market_mid');
            $table->unsignedBigInteger('market_high');
            $table->unsignedBigInteger('trade_in_low');
            $table->unsignedBigInteger('trade_in_high');
            $table->string('confidence', 20);
            $table->unsignedSmallInteger('comparable_count');
            $table->timestampTz('data_as_of');
            $table->timestampTz('valid_until');
            $table->boolean('requires_physical_inspection')->default(true);
            $table->text('disclaimer');
            $table->jsonb('adjustments')->nullable();
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at');
            $table->timestampsTz();

            $table->unique(['appraisal_id', 'version']);
            $table->index(['appraisal_id', 'published_at']);
        });

        Schema::create('appraisal_comparables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appraisal_result_id')->constrained()->cascadeOnDelete();
            $table->string('source_code', 60);
            $table->char('external_reference_hash', 64)->nullable();
            $table->string('make', 80);
            $table->string('model', 100);
            $table->string('variant', 120)->nullable();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('mileage')->nullable();
            $table->unsignedBigInteger('listing_price');
            $table->string('city', 100)->nullable();
            $table->timestampTz('observed_at');
            $table->decimal('similarity_score', 5, 4);
            $table->boolean('is_outlier')->default(false);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['appraisal_result_id', 'is_outlier']);
            $table->index(['source_code', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_comparables');
        Schema::dropIfExists('appraisal_results');
        Schema::dropIfExists('appraisal_status_histories');
        Schema::dropIfExists('appraisal_photos');
        Schema::dropIfExists('appraisals');
        Schema::dropIfExists('vehicles');
    }
};
