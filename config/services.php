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

    'fiuu' => [
        'enabled' => env('FIUU_ENABLED', false),
        'env' => env('FIUU_ENV', 'sandbox'),
        'merchant_id' => env('FIUU_MERCHANT_ID', ''),
        'verify_key' => env('FIUU_VERIFY_KEY', ''),
        'secret_key' => env('FIUU_SECRET_KEY', ''),
        'currency' => env('FIUU_CURRENCY', 'MYR'),
        'country' => env('FIUU_COUNTRY', 'MY'),
        'pay_base_url' => env('FIUU_PAY_BASE_URL', 'https://sandbox.merchant.razer.com/RMS/pay'),
        'payment_method' => env('FIUU_PAYMENT_METHOD'),
        'duitnow_channel' => env('FIUU_DUITNOW_CHANNEL'),
    ],

];
