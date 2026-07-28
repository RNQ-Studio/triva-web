<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appraisal_ai_agent_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('appraisal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_data_source_id')
                ->nullable()
                ->constrained('market_data_sources')
                ->nullOnDelete();
            $table->string('phase', 30);
            $table->string('status', 30);
            $table->string('model', 100);
            $table->string('prompt_version', 40);
            $table->char('input_hash', 64);
            $table->string('response_id', 255)->nullable();
            $table->unsignedSmallInteger('candidate_count')->default(0);
            $table->unsignedSmallInteger('accepted_count')->default(0);
            $table->jsonb('sources')->nullable();
            $table->jsonb('usage')->nullable();
            $table->jsonb('output')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->text('error_message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['appraisal_id', 'created_at']);
            $table->index(['phase', 'status', 'created_at']);
            $table->index('input_hash');
        });

        DB::table('market_data_sources')->insert([
            'code' => 'openai_market_research',
            'name' => 'OpenAI — riset pasar dua-agent',
            'type' => 'ai_research',
            'status' => 'draft',
            'base_url' => 'https://api.openai.com',
            'rate_limit_per_minute' => 6,
            'retention_days' => 30,
            'settings' => json_encode([
                'allowed_domains' => [
                    'www.olx.co.id',
                    'olx.co.id',
                ],
                'maximum_candidates' => 12,
                'minimum_reviewer_confidence' => 'medium',
                'search_context_size' => 'medium',
            ], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_ai_agent_runs');

        DB::table('market_data_sources')
            ->where('code', 'openai_market_research')
            ->delete();
    }
};
