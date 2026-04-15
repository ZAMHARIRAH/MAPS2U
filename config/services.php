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

    'gmail_oauth' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_GMAIL_REDIRECT_URI'),
        'scopes' => array_filter(array_map('trim', explode(',', env('GOOGLE_GMAIL_SCOPES', 'https://www.googleapis.com/auth/gmail.send')))),
        'from' => env('GOOGLE_GMAIL_FROM'),
        'from_name' => env('GOOGLE_GMAIL_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'MAPS2U'))),
        'refresh_token' => env('GOOGLE_GMAIL_REFRESH_TOKEN'),
    ],

];
