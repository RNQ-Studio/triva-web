<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_usage_events')) {
            return;
        }

        Schema::create('menu_usage_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            // Sengaja string, bukan enum: menu baru pada aplikasi yang lebih
            // baru harus tetap tercatat tanpa migrasi schema lebih dulu.
            $table->string('menu_key', 64);
            $table->enum('source', ['android', 'web', 'landing_page']);
            $table->timestampTz('occurred_at');
            $table->string('app_version', 50)->nullable();
            $table->string('app_build', 50)->nullable();
            $table->timestampsTz();

            $table->index(['occurred_at', 'menu_key']);
            $table->index(['menu_key', 'occurred_at']);
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_usage_events');
    }
};
