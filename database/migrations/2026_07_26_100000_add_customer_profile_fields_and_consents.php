<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('city', 100)->nullable()->after('phone');
            $table->timestampTz('service_consent_at')->nullable()->after('city');
            $table->boolean('marketing_consent')->default(false)->after('service_consent_at');
            $table->timestampTz('marketing_consent_updated_at')->nullable()->after('marketing_consent');
        });

        Schema::create('customer_consents', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->boolean('granted');
            $table->string('policy_version', 30);
            $table->string('source', 40)->default('mobile');
            $table->timestampTz('captured_at');
            $table->timestampsTz();

            $table->index(['user_id', 'type', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_consents');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'city',
                'service_consent_at',
                'marketing_consent',
                'marketing_consent_updated_at',
            ]);
        });
    }
};
