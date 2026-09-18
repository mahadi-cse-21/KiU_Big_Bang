<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LLM Provider Configuration
    |--------------------------------------------------------------------------
    | Supported: "gemini", "openai", "custom", "fallback"
    */
    'provider' => env('LLM_PROVIDER', 'gemini'),

    'timeout' => env('LLM_TIMEOUT_SECONDS', 15),

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Optimization & Replay Numerical Tolerance
    |--------------------------------------------------------------------------
    | Canonical tolerance for floating-point comparisons (0.01 kWh or 0.01 BDT)
    */
    'tolerance' => 0.01,
];
