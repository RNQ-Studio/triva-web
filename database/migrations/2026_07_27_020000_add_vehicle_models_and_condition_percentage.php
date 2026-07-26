<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_make_id')
                ->constrained('vehicle_makes')
                ->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('source_url', 2048)->nullable();
            $table->date('source_checked_at')->nullable();
            $table->timestampsTz();

            $table->unique(['vehicle_make_id', 'slug']);
            $table->index([
                'vehicle_make_id',
                'is_active',
                'sort_order',
            ], 'vehicle_models_picker_index');
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('vehicle_model_id')
                ->nullable()
                ->constrained('vehicle_models')
                ->nullOnDelete();
        });

        Schema::table('appraisals', function (Blueprint $table): void {
            $table->unsignedSmallInteger('condition_percentage')->default(90);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appraisals ADD CONSTRAINT appraisals_condition_percentage_check
                CHECK (condition_percentage BETWEEN 0 AND 100)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE appraisals DROP CONSTRAINT IF EXISTS appraisals_condition_percentage_check'
            );
        }

        Schema::table('appraisals', function (Blueprint $table): void {
            $table->dropColumn('condition_percentage');
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_model_id');
        });

        Schema::dropIfExists('vehicle_models');
    }
};
