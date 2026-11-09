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
        // Translations from QuranEnc.com. An edition is imported only once its licence is confirmed
        // in writing: set its *_LICENCE_CONFIRMED to the reference of that written permission.
        'quranenc' => [
            'api_base' => env('QURANENC_API_BASE', 'https://quranenc.com/api/v1'),
            // Polite downloading: the gap between requests, the timeout, and attempts per request.
            'delay_ms' => (int) env('QURANENC_DELAY_MS', 500),
            'timeout' => (int) env('QURANENC_TIMEOUT', 60),
            'max_attempts' => (int) env('QURANENC_MAX_ATTEMPTS', 4),
            // A CA certificate file, for PHP installs without one. Certificate checks always stay on.
            'ca_bundle' => env('QURANENC_CA_BUNDLE'),
            'editions' => [
                'bengali_zakaria' => [
                    'identifier' => 'bn.zakaria',
                    'translator' => 'Dr. Abu Bakr Muhammad Zakaria',
                    'licence_confirmed' => env('QURANENC_BENGALI_ZAKARIA_LICENCE_CONFIRMED'),
                ],
                'english_rwwad' => [
                    'identifier' => 'en.rwwad',
                    'translator' => 'Rowwad Translation Center',
                    'licence_confirmed' => env('QURANENC_ENGLISH_RWWAD_LICENCE_CONFIRMED'),
                ],
            ],
        ],
        // Talal Itani's Clear Quran (quran:import-clearquran). Its published terms, checked on
        // 2026-11-09 at https://clearquran.com/download/, permit storage and distribution.
        'clearquran' => [
            'licence' => [
                'permission_url' => 'https://clearquran.com/download/',
                'licence_url' => 'https://creativecommons.org/licenses/by-nd/4.0/',
                'file_licence_line' => 'CC BY-ND 4.0 (https://creativecommons.org/licenses/by-nd/4.0/)',
                'summary' => 'CC BY-ND 4.0. The copyright holder also permits using, storing, converting and distributing the files, including commercially, without asking, on condition that no words are changed, added or removed and the credit is included.',
                'credit' => 'Translation by Talal Itani, ClearQuran.com',
            ],
            'editions' => [
                'allah' => ['identifier' => 'en.clearquran', 'name' => 'Clear Quran (Allah Edition)', 'file' => 'clearquran-allah-edition.txt'],
                'god' => ['identifier' => 'en.clearquran-god', 'name' => 'Clear Quran (God Edition)', 'file' => 'clearquran-god-edition.txt'],
            ],
        ],
        // Arabic text for quran:import-arabic. Tanzil's terms: CC BY 3.0, verbatim copies only.
        'tanzil' => [
            'download_url' => env('TANZIL_DOWNLOAD_URL', 'https://tanzil.net/pub/download/index.php'),
            'metadata_url' => env('TANZIL_METADATA_URL', 'https://tanzil.net/res/text/metadata/quran-data.xml'),
        ],
    ],

    'hadith' => [
        'api_base' => env('HADITH_API_BASE', 'https://cdn.jsdelivr.net/gh/fawazahmed0/hadith-api@1/editions'),
    ],

    'exports' => [
        'ttl_hours' => (int) env('DATA_EXPORT_TTL_HOURS', 72),
    ],

    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', ''))))),

    'password_reset_url' => env('PASSWORD_RESET_URL', 'deenmate://reset-password'),

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        // Absolute path to a Firebase service-account JSON file kept outside the web root.
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],
];
