<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ollama' => [
        'host'            => env('OLLAMA_HOST', 'https://ollama.andres-wong.com'),
        'embed_model'     => env('OLLAMA_EMBED_MODEL', 'mxbai-embed-large:latest'),
        'timeout'         => env('OLLAMA_TIMEOUT', 30),
    ],

    // ⚠️ EXPERIMENTAL — knowledge consolidation LLM (OpenAI-compatible).
    // Powers POST /api/v1/memory/consolidate only; the normal search path
    // never touches this. Prod default: DeepSeek deepseek-v4-flash.
    // Routes through frgo (self-hosted router). DEFAULT model = deepseek-flash
    // for RESPONSIVENESS: deepseek-pro is a reasoning model and takes ~35s on
    // 10-memory sets, tripping client/MCP timeouts ("consolidator down"). flash
    // (still deepseek-v4) is ~2-3x faster; the citation guard neutralizes its
    // CoT-leak and the structured provenance[] is authoritative anyway. Callers
    // who want pro's quality pass {model:"deepseek-pro"} per request.
    // The consolidator runs as a DELTA EXTRACTOR (see ConsolidatorService).
    'consolidator' => [
        'enabled'    => env('CONSOLIDATOR_ENABLED', false),
        'base_url'   => env('CONSOLIDATOR_BASE_URL', 'https://frgo.purpleai.mx/v1'),
        'api_key'    => env('CONSOLIDATOR_API_KEY'),
        'model'      => env('CONSOLIDATOR_MODEL', 'deepseek-flash'),
        'timeout'    => env('CONSOLIDATOR_TIMEOUT', 120),
        'max_tokens' => env('CONSOLIDATOR_MAX_TOKENS', 8192),
        // DeepSeek-v4 only: send chat_template_kwargs.thinking=false to cut the
        // reasoning latency that makes consolidate ~40s (trips client timeouts).
        'disable_thinking' => env('CONSOLIDATOR_DISABLE_THINKING', true),
    ],

];
