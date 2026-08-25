<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kampanye SSC (Special Service Campaign) Toyota beserta cakupan nomor
 * rangkanya.
 *
 * Notulensi 19 Agustus 2026 meminta pelanggan bisa memasukkan No. Rangka
 * sendiri untuk mengetahui unitnya terlibat SSC atau tidak. Data kampanye
 * datang dari TAM dan diinput cabang, jadi tabelnya kosong sampai diisi dan
 * pemeriksaan menjawab "belum dapat dipastikan" alih-alih menebak.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('toyota_ssc_campaigns')) {
            return;
        }

        Schema::create('toyota_ssc_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('campaign_code', 40)->unique();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('vehicle_model', 100)->nullable();
            $table->unsignedSmallInteger('year_from')->nullable();
            $table->unsignedSmallInteger('year_to')->nullable();
            // Awalan nomor rangka yang tercakup. Kosong berarti seluruh unit
            // pada model dan rentang tahun di atas ikut tercakup.
            $table->json('vin_prefixes')->nullable();
            $table->string('recommended_action', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('source_reference', 200);
            $table->timestamps();

            $table->index(['is_active', 'effective_from'], 'ssc_campaigns_active_idx');
            $table->index('vehicle_model', 'ssc_campaigns_model_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toyota_ssc_campaigns');
    }
};
