<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider Names
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the AI providers below should be the
    | default for AI operations when no explicit provider is provided
    | for the operation. This should be any provider defined below.
    |
    */

    'default' => 'openai',
    'default_for_images' => 'gemini',
    'default_for_audio' => 'openai',
    'default_for_transcription' => 'openai',
    'default_for_embeddings' => 'openai',
    'default_for_reranking' => 'cohere',

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Below you may configure caching strategies for AI related operations
    | such as embedding generation. You are free to adjust these values
    | based on your application's available caching stores and needs.
    |
    */

    'caching' => [
        'embeddings' => [
            'cache' => false,
            'store' => env('CACHE_STORE', 'database'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Providers
    |--------------------------------------------------------------------------
    |
    | Below are each of your AI providers defined for this application. Each
    | represents an AI provider and API key combination which can be used
    | to perform tasks like text, image, and audio creation via agents.
    |
    */

    'providers' => [
        'anthropic' => [
            'driver' => 'anthropic',
            'key' => env('ANTHROPIC_API_KEY'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
        ],

        'azure' => [
            'driver' => 'azure',
            'key' => env('AZURE_OPENAI_API_KEY'),
            'url' => env('AZURE_OPENAI_URL'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-10-21'),
            'deployment' => env('AZURE_OPENAI_DEPLOYMENT', 'gpt-4o'),
            'embedding_deployment' => env('AZURE_OPENAI_EMBEDDING_DEPLOYMENT', 'text-embedding-3-small'),
        ],

        'cohere' => [
            'driver' => 'cohere',
            'key' => env('COHERE_API_KEY'),
        ],

        'deepseek' => [
            'driver' => 'deepseek',
            'key' => env('DEEPSEEK_API_KEY'),
        ],

        'eleven' => [
            'driver' => 'eleven',
            'key' => env('ELEVENLABS_API_KEY'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'key' => env('GEMINI_API_KEY'),
        ],

        'groq' => [
            'driver' => 'groq',
            'key' => env('GROQ_API_KEY'),
            'url' => env('GROQ_URL', 'https://api.groq.com/openai/v1'),
        ],

        'jina' => [
            'driver' => 'jina',
            'key' => env('JINA_API_KEY'),
        ],

        'mistral' => [
            'driver' => 'mistral',
            'key' => env('MISTRAL_API_KEY'),
        ],

        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],

        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
        ],

        'openrouter' => [
            'driver' => 'openrouter',
            'key' => env('OPENROUTER_API_KEY'),
        ],

        'voyageai' => [
            'driver' => 'voyageai',
            'key' => env('VOYAGEAI_API_KEY'),
        ],

        'xai' => [
            'driver' => 'xai',
            'key' => env('XAI_API_KEY'),
        ],
    ],

    'inference' => [
        'deepseek' => [
            'completions_url' => env('DEEPSEEK_COMPLETIONS_URL', 'https://api.deepseek.com/chat/completions'),
            'timeout_seconds' => (int) env('DEEPSEEK_TIMEOUT', 45),
            'default_model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        ],
    ],

    'command_bar' => [
        'enabled' => (bool) env('AI_COMMAND_BAR_ENABLED', true),
        'max_tool_rounds' => max(1, min(12, (int) env('AI_COMMAND_BAR_MAX_TOOL_ROUNDS', 6))),
        'max_tokens' => max(256, min(4096, (int) env('AI_COMMAND_BAR_MAX_TOKENS', 1800))),
        'temperature' => (float) env('AI_COMMAND_BAR_TEMPERATURE', 0.35),
        'max_attachment_files' => max(1, min(5, (int) env('AI_COMMAND_BAR_MAX_ATTACHMENT_FILES', 3))),
        'max_attachment_chars' => max(2000, min(50000, (int) env('AI_COMMAND_BAR_MAX_ATTACHMENT_CHARS', 12000))),
        'max_wall_seconds' => max(15, min(300, (int) env('AI_COMMAND_BAR_MAX_WALL_SECONDS', 240))),

        /*
        | История диалога: storage — localStorage, request — валидация API,
        | llm — сколько последних реплик уходит в модель. *_extended — режим «Расширить память».
        */
        'history' => [
            'tiers' => [
                'default' => [
                    'storage' => 40,
                    'request' => 20,
                    'llm' => 10,
                    'storage_extended' => 80,
                    'request_extended' => 40,
                    'llm_extended' => 20,
                    'can_extend' => true,
                ],
                'supervisor' => [
                    'storage' => 80,
                    'request' => 40,
                    'llm' => 20,
                    'storage_extended' => 160,
                    'request_extended' => 80,
                    'llm_extended' => 40,
                    'can_extend' => true,
                ],
                'admin' => [
                    'storage' => 200,
                    'request' => 100,
                    'llm' => 50,
                    'storage_extended' => 200,
                    'request_extended' => 100,
                    'llm_extended' => 50,
                    'can_extend' => false,
                ],
            ],
        ],
    ],

    'sales_book' => [
        'article_max_chars' => max(2000, min(50000, (int) env('AI_SALES_BOOK_ARTICLE_MAX_CHARS', 12000))),
        'excerpt_chars' => max(80, min(500, (int) env('AI_SALES_BOOK_EXCERPT_CHARS', 240))),
    ],

    'order_intake' => [
        'enabled' => (bool) env('AI_ORDER_INTAKE_ENABLED', true),
        'max_text_chars' => max(2000, min(50000, (int) env('AI_ORDER_INTAKE_MAX_TEXT_CHARS', 12000))),
        'max_tokens' => max(512, min(4096, (int) env('AI_ORDER_INTAKE_MAX_TOKENS', 2500))),
        'temperature' => (float) env('AI_ORDER_INTAKE_TEMPERATURE', 0.1),
    ],

    'mail_analysis' => [
        'max_tokens' => max(256, min(4096, (int) env('AI_MAIL_ANALYSIS_MAX_TOKENS', 1200))),
        'temperature' => (float) env('AI_MAIL_ANALYSIS_TEMPERATURE', 0.3),
    ],

    'insight_drafts' => [
        'max_tokens' => max(256, min(2048, (int) env('AI_INSIGHT_DRAFTS_MAX_TOKENS', 900))),
        'temperature' => (float) env('AI_INSIGHT_DRAFTS_TEMPERATURE', 0.2),
        'auto_extract_from_inbound_mail' => (bool) env('AI_INSIGHT_DRAFTS_AUTO_EXTRACT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Аналитика взаимодействий с AI (обезличенные промпты в БД)
    |--------------------------------------------------------------------------
    */

    'analytics' => [
        'enabled' => (bool) env('AI_ANALYTICS_ENABLED', true),
        'max_prompt_storage_chars' => max(500, min(8000, (int) env('AI_ANALYTICS_MAX_PROMPT_CHARS', 2000))),
        'max_reply_storage_chars' => max(500, min(16000, (int) env('AI_ANALYTICS_MAX_REPLY_CHARS', 4000))),
        'insights_default_days' => max(1, min(365, (int) env('AI_ANALYTICS_INSIGHTS_DAYS', 30))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Обезличивание перед внешним LLM (уровень 3)
    |--------------------------------------------------------------------------
    |
    | Профили: command_bar — операционный ассистент (id заказов сохраняем),
    | trainer — тренажёр (агрессивнее, без числовых id).
    |
    */

    'sanitizer' => [
        'enabled' => (bool) env('AI_EXTERNAL_SANITIZER_ENABLED', true),
        'profiles' => [
            'default' => [
                'redact_pii_patterns' => true,
                'redact_entity_ids' => false,
                'redact_sensitive_fields' => true,
            ],
            'command_bar' => [
                'redact_pii_patterns' => true,
                'redact_entity_ids' => false,
                'redact_sensitive_fields' => true,
            ],
            'trainer' => [
                'redact_pii_patterns' => true,
                'redact_entity_ids' => true,
                'redact_sensitive_fields' => true,
            ],
        ],
    ],

];
