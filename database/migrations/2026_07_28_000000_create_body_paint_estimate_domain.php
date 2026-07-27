<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_paint_price_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('matrix_code', 64);
            $table->string('item_code', 64);
            $table->unsignedSmallInteger('version');
            $table->foreignUuid('service_location_id')
                ->nullable()
                ->constrained('toyota_service_locations')
                ->restrictOnDelete();
            $table->foreignId('vehicle_make_id')
                ->nullable()
                ->constrained('vehicle_makes')
                ->restrictOnDelete();
            $table->foreignId('vehicle_model_id')
                ->nullable()
                ->constrained('vehicle_models')
                ->restrictOnDelete();
            $table->string('vehicle_class', 40)->nullable();
            $table->string('panel_code', 64);
            $table->string('damage_type', 64);
            $table->string('severity', 20);
            $table->string('work_type', 20);
            $table->unsignedBigInteger('labor_low')->default(0);
            $table->unsignedBigInteger('labor_high')->default(0);
            $table->unsignedBigInteger('material_low')->default(0);
            $table->unsignedBigInteger('material_high')->default(0);
            $table->unsignedBigInteger('parts_low')->default(0);
            $table->unsignedBigInteger('parts_high')->default(0);
            $table->unsignedBigInteger('other_low')->default(0);
            $table->unsignedBigInteger('other_high')->default(0);
            $table->unsignedSmallInteger('duration_min_hours')->default(1);
            $table->unsignedSmallInteger('duration_max_hours')->default(1);
            $table->boolean('is_high_risk')->default(false);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('source_reference');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['matrix_code', 'item_code', 'version'],
                'bp_price_item_version_unique',
            );
            $table->index(
                [
                    'panel_code',
                    'damage_type',
                    'severity',
                    'is_active',
                    'effective_from',
                ],
                'bp_price_lookup',
            );
            $table->index(['service_location_id', 'vehicle_make_id']);
        });

        Schema::create('body_paint_estimates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference_no', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('appraisal_id')
                ->nullable()
                ->constrained('appraisals')
                ->restrictOnDelete();
            $table->foreignUuid('service_location_id')
                ->nullable()
                ->constrained('toyota_service_locations')
                ->restrictOnDelete();
            $table->foreignId('assigned_estimator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 40)->default('draft');
            $table->text('customer_notes')->nullable();
            $table->string('campaign_source', 100)->nullable();
            $table->jsonb('campaign_metadata')->nullable();
            $table->unsignedBigInteger('engine_total_low')->nullable();
            $table->unsignedBigInteger('engine_total_high')->nullable();
            $table->unsignedBigInteger('published_total_low')->nullable();
            $table->unsignedBigInteger('published_total_high')->nullable();
            $table->unsignedSmallInteger('published_duration_min_days')->nullable();
            $table->unsignedSmallInteger('published_duration_max_days')->nullable();
            $table->unsignedSmallInteger('current_version')->default(0);
            $table->boolean('has_high_risk_damage')->default(false);
            $table->boolean('requires_physical_inspection')->default(true);
            $table->uuid('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->timestampTz('submitted_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('valid_until')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('last_status_changed_at');
            $table->timestampsTz();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['status', 'due_at']);
            $table->index(['assigned_estimator_id', 'status']);
            $table->index(['service_location_id', 'status']);
            $table->index(['appraisal_id', 'created_at']);
        });

        Schema::create('body_paint_estimate_damages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('estimate_id')
                ->constrained('body_paint_estimates')
                ->cascadeOnDelete();
            $table->string('panel_code', 64);
            $table->string('damage_type', 64);
            $table->string('customer_severity', 20);
            $table->string('estimator_severity', 20)->nullable();
            $table->text('customer_note')->nullable();
            $table->text('estimator_note')->nullable();
            $table->boolean('is_high_risk')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->unique(
                ['estimate_id', 'panel_code', 'damage_type'],
                'bp_estimate_damage_unique',
            );
            $table->index(['estimate_id', 'sort_order']);
        });

        Schema::create('body_paint_damage_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('estimate_id')
                ->constrained('body_paint_estimates')
                ->cascadeOnDelete();
            $table->foreignUuid('damage_id')
                ->nullable()
                ->constrained('body_paint_estimate_damages')
                ->cascadeOnDelete();
            $table->foreignUuid('asset_id')->unique()->constrained()->restrictOnDelete();
            $table->string('photo_type', 20);
            $table->string('review_status', 20)->default('pending');
            $table->string('rejection_reason_code', 64)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();

            $table->index(['estimate_id', 'photo_type']);
            $table->index(['damage_id', 'review_status']);
        });

        Schema::create('body_paint_estimate_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('estimate_id')
                ->constrained('body_paint_estimates')
                ->cascadeOnDelete();
            $table->foreignUuid('damage_id')
                ->nullable()
                ->constrained('body_paint_estimate_damages')
                ->nullOnDelete();
            $table->foreignUuid('price_item_id')
                ->nullable()
                ->constrained('body_paint_price_items')
                ->restrictOnDelete();
            $table->unsignedSmallInteger('estimate_version')->nullable();
            $table->string('matrix_code', 64)->nullable();
            $table->unsignedSmallInteger('matrix_version')->nullable();
            $table->string('panel_code', 64);
            $table->string('damage_type', 64);
            $table->string('severity', 20);
            $table->string('work_type', 20);
            $table->unsignedBigInteger('labor_low')->default(0);
            $table->unsignedBigInteger('labor_high')->default(0);
            $table->unsignedBigInteger('material_low')->default(0);
            $table->unsignedBigInteger('material_high')->default(0);
            $table->unsignedBigInteger('parts_low')->default(0);
            $table->unsignedBigInteger('parts_high')->default(0);
            $table->unsignedBigInteger('other_low')->default(0);
            $table->unsignedBigInteger('other_high')->default(0);
            $table->unsignedSmallInteger('duration_min_hours')->default(1);
            $table->unsignedSmallInteger('duration_max_hours')->default(1);
            $table->text('recommendation')->nullable();
            $table->boolean('is_engine_item')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();

            $table->index(['estimate_id', 'estimate_version', 'sort_order']);
            $table->index(['price_item_id', 'estimate_id']);
        });

        Schema::create('body_paint_estimate_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('estimate_id')
                ->constrained('body_paint_estimates')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->unsignedBigInteger('total_low');
            $table->unsignedBigInteger('total_high');
            $table->unsignedSmallInteger('duration_min_days');
            $table->unsignedSmallInteger('duration_max_days');
            $table->jsonb('assumptions');
            $table->text('disclaimer');
            $table->string('override_reason_code', 64)->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('published_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at');
            $table->timestampsTz();

            $table->unique(['estimate_id', 'version']);
        });

        Schema::create('body_paint_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('estimate_id')
                ->constrained('body_paint_estimates')
                ->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('event', 64);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->boolean('user_visible')->default(true);
            $table->foreignId('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('actor_type', 30);
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['estimate_id', 'created_at']);
        });

        Schema::table('toyota_service_bookings', function (Blueprint $table): void {
            $table->foreign('source_bp_estimate_id', 'tsb_source_bp_fk')
                ->references('id')
                ->on('body_paint_estimates')
                ->restrictOnDelete();
        });

        DB::statement(
            "ALTER TABLE body_paint_price_items
             ADD CONSTRAINT bp_price_severity_check
             CHECK (severity IN ('light', 'medium', 'heavy'))"
        );
        DB::statement(
            "ALTER TABLE body_paint_price_items
             ADD CONSTRAINT bp_price_work_type_check
             CHECK (work_type IN ('inspect', 'repair', 'replace', 'paint', 'polish'))"
        );
        DB::statement(
            'ALTER TABLE body_paint_price_items
             ADD CONSTRAINT bp_price_amounts_check
             CHECK (
                labor_low <= labor_high
                AND material_low <= material_high
                AND parts_low <= parts_high
                AND other_low <= other_high
                AND duration_min_hours > 0
                AND duration_min_hours <= duration_max_hours
                AND (effective_to IS NULL OR effective_to >= effective_from)
             )'
        );
        DB::statement(
            "ALTER TABLE body_paint_estimates
             ADD CONSTRAINT bp_estimate_status_check
             CHECK (status IN (
                'draft', 'submitted', 'auto_estimated', 'manual_review',
                'under_estimator_review', 'needs_customer_action',
                'estimate_ready', 'booking_requested',
                'inspection_scheduled', 'accepted', 'declined',
                'expired', 'cancelled'
             ))"
        );
        DB::statement(
            "ALTER TABLE body_paint_estimate_damages
             ADD CONSTRAINT bp_damage_severity_check
             CHECK (
                customer_severity IN ('light', 'medium', 'heavy', 'unsure')
                AND (
                    estimator_severity IS NULL
                    OR estimator_severity IN ('light', 'medium', 'heavy')
                )
             )"
        );
        DB::statement(
            "ALTER TABLE body_paint_damage_photos
             ADD CONSTRAINT bp_photo_type_check
             CHECK (photo_type IN ('close', 'context'))"
        );
        DB::statement(
            "ALTER TABLE body_paint_damage_photos
             ADD CONSTRAINT bp_photo_review_check
             CHECK (review_status IN ('pending', 'approved', 'rejected'))"
        );
        DB::statement(
            "ALTER TABLE body_paint_estimate_items
             ADD CONSTRAINT bp_item_severity_check
             CHECK (severity IN ('light', 'medium', 'heavy'))"
        );
        DB::statement(
            "ALTER TABLE body_paint_estimate_items
             ADD CONSTRAINT bp_item_work_type_check
             CHECK (work_type IN ('inspect', 'repair', 'replace', 'paint', 'polish'))"
        );
        DB::statement(
            'ALTER TABLE body_paint_estimate_items
             ADD CONSTRAINT bp_item_amounts_check
             CHECK (
                labor_low <= labor_high
                AND material_low <= material_high
                AND parts_low <= parts_high
                AND other_low <= other_high
                AND duration_min_hours > 0
                AND duration_min_hours <= duration_max_hours
             )'
        );
        DB::statement(
            'ALTER TABLE body_paint_estimate_versions
             ADD CONSTRAINT bp_version_range_check
             CHECK (
                total_low <= total_high
                AND duration_min_days > 0
                AND duration_min_days <= duration_max_days
             )'
        );
    }

    public function down(): void
    {
        Schema::table('toyota_service_bookings', function (Blueprint $table): void {
            $table->dropForeign('tsb_source_bp_fk');
        });
        Schema::dropIfExists('body_paint_status_histories');
        Schema::dropIfExists('body_paint_estimate_versions');
        Schema::dropIfExists('body_paint_estimate_items');
        Schema::dropIfExists('body_paint_damage_photos');
        Schema::dropIfExists('body_paint_estimate_damages');
        Schema::dropIfExists('body_paint_estimates');
        Schema::dropIfExists('body_paint_price_items');
    }
};
