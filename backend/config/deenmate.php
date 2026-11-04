<?php

return [
    'ai' => [
        // anthropic | openai_compatible | none
        'provider' => env('AI_PROVIDER', 'none'),
        'api_key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'claude-opus-5-5'),
        // Only used by openai_compatible (and optional Anthropic gateways).
        'base_url' => env('AI_BASE_URL'),
        // Anthropic thinking effort: low | medium | high | xhigh | max
        'effort' => env('AI_EFFORT', 'medium'),
        'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 8000),
        'request_timeout' => (int) env('AI_REQUEST_TIMEOUT', 120),
        'history_turns' => (int) env('AI_HISTORY_TURNS', 6),
        'retrieval_limit' => (int) env('AI_RETRIEVAL_LIMIT', 8),
        'max_question_chars' => (int) env('AI_MAX_QUESTION_CHARS', 1500),
        'requests_per_minute' => (int) env('AI_REQUESTS_PER_MINUTE', 6),
        'daily_token_limit' => (int) env('AI_DAILY_TOKEN_LIMIT', 60000),
    ],

    'quran' => [
        'api_base' => env('QURAN_API_BASE', 'https://api.alquran.cloud/v1'),
        'text_edition' => env('QURAN_TEXT_EDITION', 'quran-uthmani'),
        'transliteration_edition' => env('QURAN_TRANSLITERATION_EDITION', 'en.transliteration'),
        'translation_editions' => array_filter(explode(',', env('QURAN_TRANSLATION_EDITIONS', 'en.pickthall,bn.bengali'))),
    ],

    'hadith' => [
        'api_base' => env('HADITH_API_BASE', 'https://cdn.jsdelivr.net/gh/fawazahmed0/hadith-api@1/editions'),
    ],

    'exports' => [
        'ttl_hours' => (int) env('DATA_EXPORT_TTL_HOURS', 72),
    ],

    'trusted_proxies' => array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', '127.0.0.1')))),

    'password_reset_url' => env('PASSWORD_RESET_URL', 'deenmate://reset-password'),

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        // Absolute path to a Firebase service-account JSON file kept outside the web root.
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],
];
