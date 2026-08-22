<?php

$llmDriver = strtolower((string) env('LLM_DRIVER', 'openai')) === 'ollama'
    ? 'ollama'
    : 'openai';

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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'openai' => [
        'driver' => $llmDriver,
        'api_key' => trim((string) env('OPENAI_API_KEY', '')),
        'base_url' => rtrim((string) env(
            'OPENAI_BASE_URL',
            $llmDriver === 'ollama' ? 'http://127.0.0.1:11434/v1' : 'https://api.openai.com/v1'
        ), '/'),
        'model' => (string) env(
            'OPENAI_MODEL',
            $llmDriver === 'ollama' ? 'qwen2.5:14b' : 'gpt-4o-mini'
        ),
        'timeout' => (int) env(
            'OPENAI_TIMEOUT',
            $llmDriver === 'ollama' ? 300 : 120
        ),
        'fallback_to_openai' => filter_var(env('LLM_FALLBACK_OPENAI', false), FILTER_VALIDATE_BOOLEAN),
        'cloud_base_url' => rtrim((string) env('OPENAI_CLOUD_BASE_URL', 'https://api.openai.com/v1'), '/'),
        'cloud_model' => (string) env('OPENAI_CLOUD_MODEL', 'gpt-4o-mini'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', env('APP_URL').'/auth/facebook/callback'),
    ],

];
