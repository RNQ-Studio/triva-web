<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otoxpert_workshops', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('partner_code', 100)->nullable()->unique();
            $table->string('name');
            $table->text('address');
            $table->string('province', 100);
            $table->string('city', 100);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->jsonb('operating_hours');
            $table->decimal('service_radius_km', 6, 2)->nullable();
            $table->boolean('supports_all_vehicle_makes')->default(false);
            $table->boolean('supports_pickup_delivery')->default(false);
            $table->unsignedInteger('confirmation_sla_minutes')->default(30);
            $table->unsignedInteger('cancellation_cutoff_hours')->default(4);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('provenance_url');
            $table->timestampTz('verified_at');
            $table->timestampsTz();

            $table->index(['city', 'is_active']);
            $table->index(['effective_from', 'effective_to']);
        });

        Schema::create('otoxpert_services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('default_lead_time_days')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestampsTz();
        });

        Schema::create('otoxpert_workshop_vehicle_makes', function (Blueprint $table): void {
            $table->foreignUuid('workshop_id')
                ->constrained('otoxpert_workshops')
                ->cascadeOnDelete();
            $table->foreignId('vehicle_make_id')
                ->constrained('vehicle_makes')
                ->cascadeOnDelete();
            $table->timestampsTz();
            $table->primary(['workshop_id', 'vehicle_make_id']);
        });

        Schema::create('otoxpert_workshop_services', function (Blueprint $table): void {
            $table->foreignUuid('workshop_id')
                ->constrained('otoxpert_workshops')
                ->cascadeOnDelete();
            $table->foreignUuid('service_id')
                ->constrained('otoxpert_services')
                ->cascadeOnDelete();
            $table->unsignedInteger('lead_time_days')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->primary(['workshop_id', 'service_id']);
        });

        Schema::create('otoxpert_workshop_service_prices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workshop_id')
                ->constrained('otoxpert_workshops')
                ->cascadeOnDelete();
            $table->foreignUuid('service_id')
                ->constrained('otoxpert_services')
                ->cascadeOnDelete();
            $table->string('price_type', 20);
            $table->unsignedBigInteger('minimum_amount');
            $table->unsignedBigInteger('maximum_amount')->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->jsonb('included_items')->nullable();
            $table->jsonb('excluded_items')->nullable();
            $table->text('disclaimer');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('source_url');
            $table->timestampTz('verified_at');
            $table->timestampsTz();

            $table->index([
                'workshop_id',
                'service_id',
                'is_active',
                'effective_from',
            ], 'otoxpert_price_lookup');
        });

        Schema::create('otoxpert_workshop_operators', function (Blueprint $table): void {
            $table->foreignUuid('workshop_id')
                ->constrained('otoxpert_workshops')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->primary(['workshop_id', 'user_id']);
        });

        Schema::create('otoxpert_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('workshop_id')
                ->constrained('otoxpert_workshops')
                ->cascadeOnDelete();
            $table->date('holiday_date');
            $table->string('name');
            $table->boolean('is_closed')->default(true);
            $table->jsonb('time_windows')->nullable();
            $table->timestampsTz();
            $table->unique(['workshop_id', 'holiday_date']);
        });

        Schema::create('otoxpert_bookings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference_no', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('workshop_id')
                ->constrained('otoxpert_workshops')
                ->restrictOnDelete();
            $table->foreignUuid('service_id')
                ->constrained('otoxpert_services')
                ->restrictOnDelete();
            $table->foreignId('assigned_operator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 40);
            $table->unsignedInteger('current_mileage');
            $table->date('last_service_date')->nullable();
            $table->text('complaint');
            $table->jsonb('symptom_codes');
            $table->boolean('pickup_delivery_requested')->default(false);
            $table->string('contact_channel', 20);
            $table->timestampTz('primary_start_at');
            $table->timestampTz('primary_end_at');
            $table->timestampTz('alternative_start_at');
            $table->timestampTz('alternative_end_at');
            $table->timestampTz('proposed_start_at')->nullable();
            $table->timestampTz('proposed_end_at')->nullable();
            $table->string('proposal_context', 20)->nullable();
            $table->text('proposal_reason')->nullable();
            $table->timestampTz('proposal_expires_at')->nullable();
            $table->timestampTz('confirmed_start_at')->nullable();
            $table->timestampTz('confirmed_end_at')->nullable();
            $table->timestampTz('reschedule_primary_start_at')->nullable();
            $table->timestampTz('reschedule_primary_end_at')->nullable();
            $table->timestampTz('reschedule_alternative_start_at')->nullable();
            $table->timestampTz('reschedule_alternative_end_at')->nullable();
            $table->text('reschedule_reason')->nullable();
            $table->string('pic_name')->nullable();
            $table->text('arrival_instructions')->nullable();
            $table->string('external_booking_number')->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->text('reason')->nullable();
            $table->text('internal_note')->nullable();
            $table->unsignedBigInteger('quoted_price_min')->nullable();
            $table->unsignedBigInteger('quoted_price_max')->nullable();
            $table->string('quoted_price_type', 20)->nullable();
            $table->string('quoted_price_currency', 3)->nullable();
            $table->text('quoted_price_source')->nullable();
            $table->date('quoted_price_valid_until')->nullable();
            $table->timestampTz('partner_consent_at');
            $table->string('partner_consent_version', 40);
            $table->string('campaign_source', 100)->nullable();
            $table->jsonb('campaign_metadata')->nullable();
            $table->string('follow_up_outcome', 100)->nullable();
            $table->uuid('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->timestampTz('submitted_at');
            $table->timestampTz('due_at');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('checked_in_at')->nullable();
            $table->timestampTz('service_started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('last_status_changed_at');
            $table->timestampsTz();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['workshop_id', 'status', 'due_at']);
            $table->index(['assigned_operator_id', 'status']);
            $table->index(['confirmed_start_at', 'confirmed_end_at']);
        });

        Schema::create('otoxpert_booking_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('booking_id')
                ->constrained('otoxpert_bookings')
                ->cascadeOnDelete();
            $table->foreignUuid('asset_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['booking_id', 'asset_id']);
            $table->unique('asset_id');
        });

        Schema::create('otoxpert_booking_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('booking_id')
                ->constrained('otoxpert_bookings')
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
            $table->index(['booking_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE otoxpert_bookings
             ADD CONSTRAINT otoxpert_booking_status_check
             CHECK (status IN (
                'awaiting_confirmation', 'alternative_proposed', 'confirmed',
                'reschedule_requested', 'checked_in', 'in_service',
                'completed', 'rejected', 'cancelled', 'expired', 'no_show'
             ))"
        );
        DB::statement(
            "ALTER TABLE otoxpert_workshop_service_prices
             ADD CONSTRAINT otoxpert_price_type_check
             CHECK (price_type IN ('from', 'range'))"
        );
        DB::statement(
            'ALTER TABLE otoxpert_workshop_service_prices
             ADD CONSTRAINT otoxpert_price_amount_check
             CHECK (
                minimum_amount > 0
                AND (maximum_amount IS NULL OR maximum_amount >= minimum_amount)
             )'
        );
        DB::statement(
            'CREATE UNIQUE INDEX otoxpert_active_duplicate_unique
             ON otoxpert_bookings (
                user_id, vehicle_id, workshop_id, service_id, primary_start_at
             )
             WHERE status IN (
                \'awaiting_confirmation\', \'alternative_proposed\',
                \'confirmed\', \'reschedule_requested\', \'checked_in\',
                \'in_service\'
             )'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('otoxpert_booking_status_histories');
        Schema::dropIfExists('otoxpert_booking_photos');
        Schema::dropIfExists('otoxpert_bookings');
        Schema::dropIfExists('otoxpert_holidays');
        Schema::dropIfExists('otoxpert_workshop_operators');
        Schema::dropIfExists('otoxpert_workshop_service_prices');
        Schema::dropIfExists('otoxpert_workshop_services');
        Schema::dropIfExists('otoxpert_workshop_vehicle_makes');
        Schema::dropIfExists('otoxpert_services');
        Schema::dropIfExists('otoxpert_workshops');
    }
};
