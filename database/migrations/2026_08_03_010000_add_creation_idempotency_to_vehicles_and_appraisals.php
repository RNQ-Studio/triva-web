<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table): void {
            $table->uuid('creation_idempotency_key')->nullable();
            $table->char('creation_idempotency_fingerprint', 64)->nullable();
            $table->unique(
                ['user_id', 'creation_idempotency_key'],
                'vehicles_user_creation_idempotency_unique',
            );
        });

        Schema::table('appraisals', function (Blueprint $table): void {
            $table->uuid('creation_idempotency_key')->nullable();
            $table->char('creation_idempotency_fingerprint', 64)->nullable();
            $table->unique(
                ['user_id', 'creation_idempotency_key'],
                'appraisals_user_creation_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('appraisals', function (Blueprint $table): void {
            $table->dropUnique('appraisals_user_creation_idempotency_unique');
            $table->dropColumn([
                'creation_idempotency_key',
                'creation_idempotency_fingerprint',
            ]);
        });

        Schema::table('vehicles', function (Blueprint $table): void {
            $table->dropUnique('vehicles_user_creation_idempotency_unique');
            $table->dropColumn([
                'creation_idempotency_key',
                'creation_idempotency_fingerprint',
            ]);
        });
    }
};
