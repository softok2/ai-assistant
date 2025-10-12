<?php

declare(strict_types=1);

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
        'api_key' => env('OPENAI_API_KEY', 'sk-1234567890abcdef1234567890abcdef1234567890'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1/'),
        'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        'organization' => env('OPENAI_ORGANIZATION', 'org-1234567890'),
        'assistant_id' => env('OPENAI_ASSISTANT_ID', 'assistant-1234567890abcdef1234567890abcdef'),
        'vector_store_id' => env('OPENAI_VECTOR_STORE_ID', 'vector-1234567890abcdef1234567890abcdef'),
    ],

    'softok2mds' => [
        'base_url' => env('SOFTOK2MDS_BASE_URL', 'https://softok2mds.example.com/api/'),
        'username' => env('SOFTOK2MDS_USERNAME', 'your-username'),
        'password' => env('SOFTOK2MDS_PASSWORD', 'your-password'),
        'projects' => explode(',', env('SOFTOK2MDS_PROJECTS', 'ccm')),
        'files' => explode(',', env('SOFTOK2MDS_FILES', 'golf-output')),
    ],
];
