<?php

return [
    'market_data' => [
        'queue' => env('APPRAISAL_MARKET_DATA_QUEUE', 'triva'),
        'timeout_seconds' => (int) env('APPRAISAL_MARKET_DATA_TIMEOUT', 20),
        'user_agent' => env(
            'APPRAISAL_MARKET_DATA_USER_AGENT',
            'TRIVA-MarketData/1.0 (authorized integration)',
        ),
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('APPRAISAL_MARKET_DATA_ALLOWED_HOSTS', 'www.olx.co.id,olx.co.id')),
        ))),
        'minimum_price' => (int) env('APPRAISAL_MARKET_MIN_PRICE', 20_000_000),
        'maximum_price' => (int) env('APPRAISAL_MARKET_MAX_PRICE', 10_000_000_000),
        'maximum_age_days' => (int) env('APPRAISAL_MARKET_MAX_AGE_DAYS', 90),
        'minimum_similarity' => (float) env('APPRAISAL_MARKET_MIN_SIMILARITY', 0.55),
        'minimum_comparables' => (int) env('APPRAISAL_MARKET_MIN_COMPARABLES', 6),
        'high_confidence_comparables' => (int) env(
            'APPRAISAL_MARKET_HIGH_CONFIDENCE_COMPARABLES',
            12,
        ),
        'maximum_stable_dispersion' => (float) env(
            'APPRAISAL_MARKET_MAX_STABLE_DISPERSION',
            0.30,
        ),
        'result_valid_days' => (int) env('APPRAISAL_RESULT_VALID_DAYS', 7),
        'dealer_margin_percent' => (float) env('APPRAISAL_DEALER_MARGIN_PERCENT', 7),

        /*
         * Koreksi pasar hasil meeting 19 Agustus 2026: harga appraisal yang
         * tersaji masih 10-15 juta di atas penawaran OLX, sehingga tiap unit
         * diturunkan 10% dan unit diesel diturunkan 20%. Angkanya sengaja
         * dibuat konfigurasi supaya cabang bisa dikoreksi tanpa rilis ulang.
         */
        'market_correction_percent' => (float) env('APPRAISAL_MARKET_CORRECTION_PERCENT', 10),
        'diesel_market_correction_percent' => (float) env(
            'APPRAISAL_DIESEL_MARKET_CORRECTION_PERCENT',
            20,
        ),

        /*
         * Batas atas seluruh potongan yang menumpuk. Tanpa batas ini, unit
         * diesel bekas banjir sekaligus tabrakan berat bisa terpotong hampir
         * 60% dan menghasilkan penawaran yang tidak masuk akal.
         */
        'maximum_total_deduction_percent' => (float) env(
            'APPRAISAL_MAX_TOTAL_DEDUCTION_PERCENT',
            45,
        ),
        'rounding' => (int) env('APPRAISAL_PRICE_ROUNDING', 500_000),
    ],
    'ai' => [
        'enabled' => filter_var(
            env('APPRAISAL_AI_FALLBACK_ENABLED', true),
            FILTER_VALIDATE_BOOL,
        ),
        'price_decision_model' => env(
            'APPRAISAL_AI_PRICE_DECISION_MODEL',
            env('APPRAISAL_AI_RESEARCH_MODEL', 'gpt-5.6-sol'),
        ),
        'research_model' => env('APPRAISAL_AI_RESEARCH_MODEL', 'gpt-5.6-sol'),
        'review_model' => env('APPRAISAL_AI_REVIEW_MODEL', 'gpt-5.6-sol'),
        'reasoning_effort' => env('APPRAISAL_AI_REASONING_EFFORT', 'low'),
        'max_output_tokens' => (int) env('APPRAISAL_AI_MAX_OUTPUT_TOKENS', 6000),
        'prompt_version' => 'appraisal_price_decision_v2',
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'project' => env('OPENAI_PROJECT'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 45),
            'connect_timeout_seconds' => (int) env('OPENAI_CONNECT_TIMEOUT_SECONDS', 10),
        ],
    ],
];
