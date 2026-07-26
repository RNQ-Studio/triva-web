<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_storage_type_check'
            );
            DB::statement(
                "ALTER TABLE assets ADD CONSTRAINT assets_storage_type_check
                CHECK (storage_type::text = ANY (ARRAY['local', 'private_local', 'gcs']::text[]))"
            );
        }

        Schema::create('vehicle_makes', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name', 80)->unique();
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->foreignId('vehicle_make_id')
                ->nullable()
                ->constrained('vehicle_makes')
                ->nullOnDelete();
            $table->foreignId('province_id')
                ->nullable()
                ->constrained('regions')
                ->nullOnDelete();
            $table->foreignId('city_id')
                ->nullable()
                ->constrained('regions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('province_id');
            $table->dropConstrainedForeignId('vehicle_make_id');
        });

        Schema::dropIfExists('vehicle_makes');

        if (DB::getDriverName() === 'pgsql') {
            DB::table('assets')
                ->where('storage_type', 'private_local')
                ->update(['storage_type' => 'local']);
            DB::statement(
                'ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_storage_type_check'
            );
            DB::statement(
                "ALTER TABLE assets ADD CONSTRAINT assets_storage_type_check
                CHECK (storage_type::text = ANY (ARRAY['local', 'gcs']::text[]))"
            );
        }
    }
};
