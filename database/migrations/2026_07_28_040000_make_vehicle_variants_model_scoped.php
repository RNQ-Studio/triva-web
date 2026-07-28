<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'WITH ranked_variants AS (
                SELECT
                    id,
                    MIN(id) OVER (PARTITION BY vehicle_model_id, slug) AS canonical_id
                FROM vehicle_variants
            )
            UPDATE vehicles
            SET vehicle_variant_id = ranked_variants.canonical_id
            FROM ranked_variants
            WHERE vehicles.vehicle_variant_id = ranked_variants.id
                AND ranked_variants.id <> ranked_variants.canonical_id'
        );

        DB::statement(
            'WITH ranked_variants AS (
                SELECT
                    id,
                    MIN(id) OVER (PARTITION BY vehicle_model_id, slug) AS canonical_id
                FROM vehicle_variants
            )
            DELETE FROM vehicle_variants
            USING ranked_variants
            WHERE vehicle_variants.id = ranked_variants.id
                AND ranked_variants.id <> ranked_variants.canonical_id'
        );

        DB::table('vehicle_variants')->update([
            'year_from' => 1950,
            'year_to' => null,
        ]);

        Schema::table('vehicle_variants', function (Blueprint $table): void {
            $table->dropUnique('vehicle_variants_model_slug_year_unique');
            $table->dropIndex('vehicle_variants_picker_index');
            $table->unique(
                ['vehicle_model_id', 'slug'],
                'vehicle_variants_model_slug_unique',
            );
            $table->index(
                ['vehicle_model_id', 'is_active', 'sort_order'],
                'vehicle_variants_model_picker_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_variants', function (Blueprint $table): void {
            $table->dropUnique('vehicle_variants_model_slug_unique');
            $table->dropIndex('vehicle_variants_model_picker_index');
            $table->unique(
                ['vehicle_model_id', 'slug', 'year_from'],
                'vehicle_variants_model_slug_year_unique',
            );
            $table->index(
                ['vehicle_model_id', 'is_active', 'year_from', 'year_to'],
                'vehicle_variants_picker_index',
            );
        });
    }
};
