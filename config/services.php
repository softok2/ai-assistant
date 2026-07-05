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
        'model' => env('OPENAI_MODEL', 'gpt-4.1-mini'),
        'vector_store_id' => env('OPENAI_VECTOR_STORE_ID'),
    ],

    'browsershot' => [
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    ],

    'softok2mds' => [
        'base_url' => env('SOFTOK2MDS_BASE_URL', 'https://softok2mds.example.com/api/'),
        'username' => env('SOFTOK2MDS_USERNAME', 'your-username'),
        'password' => env('SOFTOK2MDS_PASSWORD', 'your-password'),
        'projects' => explode(',', env('SOFTOK2MDS_PROJECTS', 'ccm')),
        'files' => explode(',', env('SOFTOK2MDS_FILES', 'golf-output')),
    ],
];
