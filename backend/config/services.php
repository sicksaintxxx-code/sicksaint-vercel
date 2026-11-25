<?php

return [

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],

    'firestore' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials_json' => env('FIREBASE_CREDENTIALS_JSON'),
        'emulator_host' => env('FIRESTORE_EMULATOR_HOST'),
        'token_expiration_minutes' => env('FIRESTORE_TOKEN_EXPIRATION', 10080),
    ],

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

];
