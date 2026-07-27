<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('toyota_service_locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name', 160);
            $table->text('address')->nullable();
            $table->string('city', 100);
            $table->string('phone', 40)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('directions_url')->nullable();
            $table->string('timezone', 60)->default('Asia/Jakarta');
            $table->boolean('supports_workshop')->default(true);
            $table->boolean('supports_ths')->default(false);
            $table->jsonb('operating_hours');
            $table->unsignedSmallInteger('confirmation_sla_minutes')->default(120);
            $table->unsignedSmallInteger('cancellation_cutoff_hours')->default(4);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('provenance_url')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampsTz();

            $table->index(['is_active', 'effective_from', 'effective_to'], 'tsl_active_effective_idx');
        });

        Schema::create('toyota_service_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 60)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('supports_workshop')->default(true);
            $table->boolean('supports_ths')->default(false);
            $table->unsignedSmallInteger('workshop_lead_time_days')->default(2);
            $table->unsignedSmallInteger('ths_lead_time_days')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampsTz();

            $table->index(
                ['is_active', 'effective_from', 'effective_to', 'sort_order'],
                'tst_active_effective_sort_idx',
            );
        });

        Schema::create('toyota_service_holidays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_location_id')
                ->nullable()
                ->constrained('toyota_service_locations')
                ->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name', 160);
            $table->boolean('is_closed')->default(true);
            $table->jsonb('time_windows')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['service_location_id', 'holiday_date', 'name'],
                'tsh_location_date_name_unique',
            );
            $table->index(['holiday_date', 'is_closed'], 'tsh_date_closed_idx');
        });

        Schema::create('toyota_ths_coverages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_location_id')
                ->constrained('toyota_service_locations')
                ->cascadeOnDelete();
            $table->string('city', 100);
            $table->decimal('latitude_min', 10, 7)->nullable();
            $table->decimal('latitude_max', 10, 7)->nullable();
            $table->decimal('longitude_min', 10, 7)->nullable();
            $table->decimal('longitude_max', 10, 7)->nullable();
            $table->boolean('is_active')->default(false);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('verification_source', 100);
            $table->timestampsTz();

            $table->unique(['service_location_id', 'city'], 'ttc_location_city_unique');
            $table->index(['city', 'is_active'], 'ttc_city_active_idx');
        });

        Schema::create('toyota_service_bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference_no', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('service_location_id')
                ->constrained('toyota_service_locations')
                ->restrictOnDelete();
            $table->foreignUuid('service_type_id')
                ->constrained('toyota_service_types')
                ->restrictOnDelete();
            $table->string('fulfillment_type', 20);
            $table->string('status', 40)->default('awaiting_confirmation');
            $table->uuid('idempotency_key');
            $table->char('idempotency_fingerprint', 64);
            $table->unsignedInteger('current_mileage');
            $table->text('complaint');
            $table->timestampTz('primary_start_at');
            $table->timestampTz('primary_end_at');
            $table->timestampTz('alternative_start_at');
            $table->timestampTz('alternative_end_at');
            $table->timestampTz('active_slot_start_at');
            $table->timestampTz('active_slot_end_at');
            $table->timestampTz('proposed_start_at')->nullable();
            $table->timestampTz('proposed_end_at')->nullable();
            $table->string('proposal_context', 20)->nullable();
            $table->text('proposal_reason')->nullable();
            $table->timestampTz('proposal_expires_at')->nullable();
            $table->string('proposed_pic_name', 120)->nullable();
            $table->text('proposed_arrival_instructions')->nullable();
            $table->string('proposed_external_booking_number', 120)->nullable();
            $table->timestampTz('confirmed_start_at')->nullable();
            $table->timestampTz('confirmed_end_at')->nullable();
            $table->timestampTz('reschedule_primary_start_at')->nullable();
            $table->timestampTz('reschedule_primary_end_at')->nullable();
            $table->timestampTz('reschedule_alternative_start_at')->nullable();
            $table->timestampTz('reschedule_alternative_end_at')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->text('ths_address')->nullable();
            $table->string('ths_city', 100)->nullable();
            $table->decimal('ths_latitude', 10, 7)->nullable();
            $table->decimal('ths_longitude', 10, 7)->nullable();
            $table->text('ths_location_notes')->nullable();
            $table->string('contact_channel', 20);
            $table->foreignId('assigned_service_advisor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('pic_name', 120)->nullable();
            $table->text('arrival_instructions')->nullable();
            $table->string('external_booking_number', 120)->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->text('reason')->nullable();
            $table->foreignUuid('source_appraisal_id')
                ->nullable()
                ->constrained('appraisals')
                ->nullOnDelete();
            $table->uuid('source_bp_estimate_id')->nullable();
            $table->string('campaign_source', 100)->nullable();
            $table->jsonb('campaign_metadata')->nullable();
            $table->timestampTz('submitted_at');
            $table->timestampTz('due_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('last_status_changed_at');
            $table->timestampsTz();

            $table->unique(['user_id', 'idempotency_key'], 'tsb_user_idempotency_unique');
            $table->index(['user_id', 'updated_at'], 'tsb_user_updated_idx');
            $table->index(['status', 'due_at'], 'tsb_status_due_idx');
            $table->index(
                ['assigned_service_advisor_id', 'status'],
                'tsb_advisor_status_idx',
            );
            $table->index(
                ['service_location_id', 'active_slot_start_at'],
                'tsb_location_active_slot_idx',
            );
            $table->index('source_bp_estimate_id', 'tsb_source_bp_idx');
        });

        Schema::create('toyota_service_booking_photos', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_booking_id')
                ->constrained('toyota_service_bookings')
                ->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->restrictOnDelete();
            $table->timestampsTz();

            $table->unique('asset_id', 'tsbp_asset_unique');
            $table->unique(
                ['service_booking_id', 'asset_id'],
                'tsbp_booking_asset_unique',
            );
        });

        Schema::create('toyota_service_booking_status_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_booking_id')
                ->constrained('toyota_service_bookings')
                ->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('event', 80);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->boolean('user_visible')->default(true);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 20)->default('system');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();

            $table->index(
                ['service_booking_id', 'created_at'],
                'tsbsh_booking_created_idx',
            );
        });

        Schema::create('vehicle_benefit_checks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('service_booking_id')
                ->nullable()
                ->constrained('toyota_service_bookings')
                ->cascadeOnDelete();
            $table->string('benefit_type', 40);
            $table->string('status', 40)->default('unknown');
            $table->timestampTz('valid_until')->nullable();
            $table->string('verification_source', 80)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['service_booking_id', 'benefit_type'],
                'vbc_booking_benefit_unique',
            );
            $table->index(['vehicle_id', 'benefit_type'], 'vbc_vehicle_benefit_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE toyota_service_locations
                 ADD CONSTRAINT tsl_configuration_check CHECK (
                    (
                        (
                            latitude IS NULL
                            AND longitude IS NULL
                        )
                        OR (
                            latitude IS NOT NULL
                            AND longitude IS NOT NULL
                            AND latitude BETWEEN -90 AND 90
                            AND longitude BETWEEN -180 AND 180
                        )
                    )
                    AND confirmation_sla_minutes > 0
                    AND cancellation_cutoff_hours >= 0
                    AND (effective_to IS NULL OR effective_to >= effective_from)
                 )'
            );
            DB::statement(
                'ALTER TABLE toyota_service_types
                 ADD CONSTRAINT tst_configuration_check CHECK (
                    workshop_lead_time_days >= 0
                    AND ths_lead_time_days >= 0
                    AND (effective_to IS NULL OR effective_to >= effective_from)
                 )'
            );
            DB::statement(
                "ALTER TABLE toyota_ths_coverages
                 ADD CONSTRAINT ttc_configuration_check CHECK (
                    (
                        (
                            latitude_min IS NULL
                            AND latitude_max IS NULL
                            AND longitude_min IS NULL
                            AND longitude_max IS NULL
                        )
                        OR (
                            latitude_min IS NOT NULL
                            AND latitude_max IS NOT NULL
                            AND longitude_min IS NOT NULL
                            AND longitude_max IS NOT NULL
                            AND latitude_min BETWEEN -90 AND 90
                            AND latitude_max BETWEEN -90 AND 90
                            AND longitude_min BETWEEN -180 AND 180
                            AND longitude_max BETWEEN -180 AND 180
                            AND latitude_min <= latitude_max
                            AND longitude_min <= longitude_max
                        )
                    )
                    AND (
                        NOT is_active
                        OR (
                            latitude_min IS NOT NULL
                            AND latitude_max IS NOT NULL
                            AND longitude_min IS NOT NULL
                            AND longitude_max IS NOT NULL
                            AND BTRIM(verification_source) <> ''
                        )
                    )
                    AND (effective_to IS NULL OR effective_to >= effective_from)
                 )"
            );
            DB::statement(
                'ALTER TABLE toyota_service_bookings
                 ADD CONSTRAINT tsb_slot_order_check CHECK (
                    primary_start_at < primary_end_at
                    AND alternative_start_at < alternative_end_at
                    AND NOT (
                        primary_start_at = alternative_start_at
                        AND primary_end_at = alternative_end_at
                    )
                    AND active_slot_start_at < active_slot_end_at
                    AND (
                        (
                            proposed_start_at IS NULL
                            AND proposed_end_at IS NULL
                            AND proposed_pic_name IS NULL
                            AND proposed_arrival_instructions IS NULL
                            AND proposed_external_booking_number IS NULL
                        )
                        OR (
                            proposed_start_at IS NOT NULL
                            AND proposed_end_at IS NOT NULL
                            AND proposed_start_at < proposed_end_at
                        )
                    )
                    AND (
                        (confirmed_start_at IS NULL AND confirmed_end_at IS NULL)
                        OR (
                            confirmed_start_at IS NOT NULL
                            AND confirmed_end_at IS NOT NULL
                            AND confirmed_start_at < confirmed_end_at
                        )
                    )
                    AND (
                        (reschedule_primary_start_at IS NULL AND reschedule_primary_end_at IS NULL)
                        OR (
                            reschedule_primary_start_at IS NOT NULL
                            AND reschedule_primary_end_at IS NOT NULL
                            AND reschedule_primary_start_at < reschedule_primary_end_at
                        )
                    )
                    AND (
                        (
                            reschedule_alternative_start_at IS NULL
                            AND reschedule_alternative_end_at IS NULL
                        )
                        OR (
                            reschedule_alternative_start_at IS NOT NULL
                            AND reschedule_alternative_end_at IS NOT NULL
                            AND reschedule_alternative_start_at < reschedule_alternative_end_at
                        )
                    )
                    AND (
                        (
                            reschedule_primary_start_at IS NULL
                            AND reschedule_primary_end_at IS NULL
                            AND reschedule_alternative_start_at IS NULL
                            AND reschedule_alternative_end_at IS NULL
                        )
                        OR (
                            reschedule_primary_start_at IS NOT NULL
                            AND reschedule_primary_end_at IS NOT NULL
                            AND reschedule_alternative_start_at IS NOT NULL
                            AND reschedule_alternative_end_at IS NOT NULL
                            AND NOT (
                                reschedule_primary_start_at = reschedule_alternative_start_at
                                AND reschedule_primary_end_at = reschedule_alternative_end_at
                            )
                        )
                    )
                 )'
            );
            DB::statement(
                "ALTER TABLE toyota_service_bookings
                 ADD CONSTRAINT tsb_enum_values_check CHECK (
                    fulfillment_type IN ('workshop', 'ths')
                    AND status IN (
                        'awaiting_confirmation',
                        'alternative_proposed',
                        'confirmed',
                        'reschedule_requested',
                        'checked_in',
                        'in_service',
                        'completed',
                        'rejected',
                        'cancelled',
                        'expired',
                        'no_show'
                    )
                    AND contact_channel IN ('whatsapp', 'phone', 'email')
                    AND (
                        proposal_context IS NULL
                        OR proposal_context IN ('initial', 'reschedule')
                    )
                 )"
            );
            DB::statement(
                'ALTER TABLE toyota_service_bookings
                 ADD CONSTRAINT tsb_proposal_validity_check CHECK (
                    (
                        proposed_start_at IS NULL
                        AND proposed_end_at IS NULL
                        AND proposal_context IS NULL
                        AND proposal_expires_at IS NULL
                    )
                    OR (
                        proposed_start_at IS NOT NULL
                        AND proposed_end_at IS NOT NULL
                        AND proposal_context IS NOT NULL
                        AND proposal_expires_at IS NOT NULL
                        AND proposal_expires_at < proposed_start_at
                    )
                 )'
            );
            DB::statement(
                "ALTER TABLE toyota_service_bookings
                 ADD CONSTRAINT tsb_ths_location_check CHECK (
                    fulfillment_type <> 'ths'
                    OR (
                        ths_address IS NOT NULL
                        AND ths_city IS NOT NULL
                        AND ths_latitude IS NOT NULL
                        AND ths_longitude IS NOT NULL
                        AND ths_latitude BETWEEN -90 AND 90
                        AND ths_longitude BETWEEN -180 AND 180
                    )
                 )"
            );
            DB::statement(
                "CREATE UNIQUE INDEX tsb_active_duplicate_unique
                 ON toyota_service_bookings (
                    vehicle_id,
                    service_type_id,
                    active_slot_start_at,
                    active_slot_end_at
                 )
                 WHERE status IN (
                    'awaiting_confirmation',
                    'alternative_proposed',
                    'confirmed',
                    'reschedule_requested',
                    'checked_in',
                    'in_service'
                 )"
            );
            DB::statement(
                "ALTER TABLE vehicle_benefit_checks
                 ADD CONSTRAINT vbc_verified_status_check CHECK (
                    benefit_type IN ('t_care', 'ssc', 'warranty')
                    AND status IN ('unknown', 'pending_verification', 'active', 'inactive')
                    AND (
                        status NOT IN ('active', 'inactive')
                        OR (
                            verification_source IN ('official_api', 'staff_manual')
                            AND verified_by IS NOT NULL
                            AND verified_at IS NOT NULL
                        )
                    )
                 )"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_benefit_checks');
        Schema::dropIfExists('toyota_service_booking_status_histories');
        Schema::dropIfExists('toyota_service_booking_photos');
        Schema::dropIfExists('toyota_service_bookings');
        Schema::dropIfExists('toyota_ths_coverages');
        Schema::dropIfExists('toyota_service_holidays');
        Schema::dropIfExists('toyota_service_types');
        Schema::dropIfExists('toyota_service_locations');
    }
};
