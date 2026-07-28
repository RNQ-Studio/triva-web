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
        'rounding' => (int) env('APPRAISAL_PRICE_ROUNDING', 500_000),
    ],
    'ai' => [
        'enabled' => filter_var(
            env('APPRAISAL_AI_FALLBACK_ENABLED', false),
            FILTER_VALIDATE_BOOL,
        ),
        'research_model' => env('APPRAISAL_AI_RESEARCH_MODEL', 'gpt-5.6-sol'),
        'review_model' => env('APPRAISAL_AI_REVIEW_MODEL', 'gpt-5.6-sol'),
        'reasoning_effort' => env('APPRAISAL_AI_REASONING_EFFORT', 'low'),
        'max_output_tokens' => (int) env('APPRAISAL_AI_MAX_OUTPUT_TOKENS', 6000),
        'prompt_version' => 'appraisal_market_agents_v1',
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'project' => env('OPENAI_PROJECT'),
            'timeout_seconds' => (int) env('OPENAI_TIMEOUT_SECONDS', 45),
            'connect_timeout_seconds' => (int) env('OPENAI_CONNECT_TIMEOUT_SECONDS', 10),
        ],
    ],
];
