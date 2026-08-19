<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->enum('source', ['android', 'web', 'landing_page']);
            $table->char('visit_key', 64);
            $table->timestampTz('occurred_at');
            $table->string('app_version', 50)->nullable();
            $table->string('app_build', 50)->nullable();
            $table->timestampsTz();

            $table->unique(['source', 'visit_key']);
            $table->index(['occurred_at', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_events');
    }
};
