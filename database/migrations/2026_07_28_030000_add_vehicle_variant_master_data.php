<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_model_id')
                ->constrained('vehicle_models')
                ->cascadeOnDelete();
            $table->string('slug', 160);
            $table->string('name', 120);
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->string('transmission', 30)->nullable();
            $table->string('fuel_type', 30)->nullable();
            $table->jsonb('aliases')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('source_url', 2048)->nullable();
            $table->date('source_checked_at')->nullable();
            $table->timestampsTz();

            $table->unique(
                ['vehicle_model_id', 'slug', 'year_from'],
                'vehicle_variants_model_slug_year_unique',
            );
            $table->index(
                ['vehicle_model_id', 'is_active', 'year_from', 'year_to'],
                'vehicle_variants_picker_index',
            );
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('vehicle_variant_id')
                ->nullable()
                ->constrained('vehicle_variants')
                ->nullOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE vehicle_variants ADD CONSTRAINT vehicle_variants_year_from_check
                CHECK (year_from >= 1950)'
            );
            DB::statement(
                'ALTER TABLE vehicle_variants ADD CONSTRAINT vehicle_variants_year_range_check
                CHECK (year_to IS NULL OR year_to >= year_from)'
            );
        }
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vehicle_variant_id');
        });

        Schema::dropIfExists('vehicle_variants');
    }
};
