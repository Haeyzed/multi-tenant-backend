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

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'from' => env('SMS_FROM', env('TWILIO_FROM')),
    ],

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    'push' => [
        'driver' => env('PUSH_DRIVER', 'null'),
    ],

    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'shipping_label' => [
        'driver' => env('SHIPPING_LABEL_DRIVER', 'manual'),
    ],

    'easypost' => [
        'api_key' => env('EASYPOST_API_KEY'),
    ],

    'amazon' => [
        'authorize_url' => env('AMAZON_AUTHORIZE_URL', 'https://sellercentral.amazon.com/apps/authorize/consent'),
        'token_url' => env('AMAZON_TOKEN_URL', 'https://api.amazon.com/auth/o2/token'),
        'api_base' => env('AMAZON_API_BASE', 'https://sellingpartnerapi-na.amazon.com'),
        'application_id' => env('AMAZON_APPLICATION_ID'),
    ],

    'ebay' => [
        'authorize_url' => env('EBAY_AUTHORIZE_URL', 'https://auth.ebay.com/oauth2/authorize'),
        'token_url' => env('EBAY_TOKEN_URL', 'https://api.ebay.com/identity/v1/oauth2/token'),
        'api_base' => env('EBAY_API_BASE', 'https://api.ebay.com'),
        'ru_name' => env('EBAY_RU_NAME'),
        'scopes' => env('EBAY_SCOPES', 'https://api.ebay.com/oauth/api_scope https://api.ebay.com/oauth/api_scope/sell.fulfillment'),
    ],

];
