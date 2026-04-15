<?php

return [
    'email' => [
        'enabled' => env('MAPS2U_EMAIL_ENABLED', false),
        'driver' => env('MAPS2U_EMAIL_DRIVER', 'gmail_api'),
        'reply_to' => env('MAPS2U_EMAIL_REPLY_TO'),
    ],
    'whatsapp' => [
        'enabled' => env('MAPS2U_WHATSAPP_ENABLED', false),
        'provider' => env('MAPS2U_WHATSAPP_PROVIDER', 'meta_cloud'),
        'token' => env('MAPS2U_WHATSAPP_TOKEN'),
        'phone_number_id' => env('MAPS2U_WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('MAPS2U_WHATSAPP_API_VERSION', 'v21.0'),
        'base_url' => env('MAPS2U_WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
        'default_country_code' => env('MAPS2U_WHATSAPP_DEFAULT_COUNTRY_CODE', '60'),
    ],
    'app_name' => env('APP_NAME', 'MAPS2U'),
];
