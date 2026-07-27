<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_programs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('program_code', 64);
            $table->unsignedSmallInteger('version');
            $table->string('partner_name');
            $table->string('program_name');
            $table->string('city', 100);
            $table->string('vehicle_model');
            $table->string('vehicle_variant');
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->unsignedBigInteger('otr_price');
            $table->unsignedBigInteger('approved_discount')->default(0);
            $table->unsignedSmallInteger('minimum_dp_basis_points');
            $table->unsignedSmallInteger('maximum_dp_basis_points');
            $table->jsonb('tenor_options');
            $table->string('formula_strategy', 40)->default('flat_rate');
            $table->string('formula_version', 40)->default('flat-v1');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('source_reference');
            $table->string('status', 20)->default('draft');
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                [
                    'program_code',
                    'version',
                    'city',
                    'vehicle_model',
                    'vehicle_variant',
                ],
                'credit_program_version_unique',
            );
            $table->index(
                [
                    'status',
                    'city',
                    'vehicle_model',
                    'effective_from',
                ],
                'credit_program_catalog_lookup',
            );
            $table->index(['effective_from', 'effective_to']);
        });

        Schema::create('credit_simulations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference_no', 40)->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('credit_program_id')
                ->constrained('credit_programs')
                ->restrictOnDelete();
            $table->foreignUuid('appraisal_id')
                ->nullable()
                ->constrained('appraisals')
                ->restrictOnDelete();
            $table->uuid('comparison_group_id')->nullable();
            $table->string('status', 24);
            $table->jsonb('program_snapshot');
            $table->jsonb('input_snapshot');
            $table->jsonb('calculation_snapshot');
            $table->string('formula_version', 40);
            $table->unsignedBigInteger('otr_price');
            $table->unsignedBigInteger('cash_down_payment');
            $table->unsignedBigInteger('trade_in_value');
            $table->unsignedBigInteger('old_vehicle_payoff');
            $table->unsignedBigInteger('trade_in_equity');
            $table->boolean('use_trade_in_as_dp');
            $table->unsignedBigInteger('approved_discount');
            $table->unsignedBigInteger('total_down_payment');
            $table->unsignedBigInteger('principal');
            $table->unsignedSmallInteger('tenor_months');
            $table->unsignedSmallInteger('annual_flat_rate_basis_points');
            $table->unsignedBigInteger('total_flat_interest');
            $table->unsignedBigInteger('monthly_installment');
            $table->unsignedBigInteger('administration_fee');
            $table->unsignedBigInteger('provision_fee');
            $table->unsignedBigInteger('upfront_insurance');
            $table->unsignedBigInteger('other_upfront_costs');
            $table->unsignedBigInteger('initial_payment');
            $table->unsignedBigInteger('total_payment');
            $table->date('valid_until')->nullable();
            $table->string('campaign_source', 100)->nullable();
            $table->uuid('idempotency_key');
            $table->char('request_fingerprint', 64);
            $table->timestampTz('saved_at');
            $table->timestampsTz();

            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['credit_program_id', 'saved_at']);
            $table->index(['appraisal_id', 'saved_at']);
            $table->index(['user_id', 'comparison_group_id']);
        });

        Schema::create('credit_follow_up_leads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference_no', 40)->unique();
            $table->foreignUuid('simulation_id')
                ->unique()
                ->constrained('credit_simulations')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_sales_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 24)->default('new');
            $table->string('contact_channel', 20);
            $table->string('consent_version', 40);
            $table->timestampTz('consent_at');
            $table->string('campaign_source', 100)->nullable();
            $table->string('outcome', 100)->nullable();
            $table->text('internal_note')->nullable();
            $table->timestampTz('contacted_at')->nullable();
            $table->timestampTz('converted_at')->nullable();
            $table->timestampsTz();

            $table->index(['assigned_sales_id', 'status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE credit_programs
             ADD CONSTRAINT credit_program_status_check
             CHECK (status IN ('draft', 'approved', 'inactive'))"
        );
        DB::statement(
            'ALTER TABLE credit_programs
             ADD CONSTRAINT credit_program_amounts_check
             CHECK (
                otr_price > 0
                AND approved_discount >= 0
                AND approved_discount <= otr_price
                AND minimum_dp_basis_points >= 0
                AND minimum_dp_basis_points <= maximum_dp_basis_points
                AND maximum_dp_basis_points <= 10000
                AND (effective_to IS NULL OR effective_to >= effective_from)
             )'
        );
        DB::statement(
            "ALTER TABLE credit_programs
             ADD CONSTRAINT credit_program_formula_check
             CHECK (formula_strategy IN ('flat_rate'))"
        );
        DB::statement(
            "ALTER TABLE credit_simulations
             ADD CONSTRAINT credit_simulation_status_check
             CHECK (status IN ('saved', 'lead_created', 'expired'))"
        );
        DB::statement(
            'ALTER TABLE credit_simulations
             ADD CONSTRAINT credit_simulation_amounts_check
             CHECK (
                otr_price > 0
                AND cash_down_payment >= 0
                AND trade_in_value >= 0
                AND old_vehicle_payoff >= 0
                AND trade_in_equity >= 0
                AND approved_discount >= 0
                AND total_down_payment >= 0
                AND total_down_payment <= otr_price
                AND principal = otr_price - total_down_payment
                AND tenor_months > 0
                AND annual_flat_rate_basis_points >= 0
                AND annual_flat_rate_basis_points <= 10000
                AND total_flat_interest >= 0
                AND monthly_installment >= 0
                AND administration_fee >= 0
                AND provision_fee >= 0
                AND upfront_insurance >= 0
                AND other_upfront_costs >= 0
                AND initial_payment >= 0
                AND total_payment >= 0
             )'
        );
        DB::statement(
            "ALTER TABLE credit_follow_up_leads
             ADD CONSTRAINT credit_lead_status_check
             CHECK (status IN ('new', 'contacted', 'converted', 'closed'))"
        );
        DB::statement(
            "ALTER TABLE credit_follow_up_leads
             ADD CONSTRAINT credit_lead_contact_channel_check
             CHECK (contact_channel IN ('whatsapp', 'phone', 'email'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_follow_up_leads');
        Schema::dropIfExists('credit_simulations');
        Schema::dropIfExists('credit_programs');
    }
};
