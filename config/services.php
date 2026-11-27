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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    // Firebase Analytics is read from its Google Analytics 4 property through the GA4 Data API.
    'ga4' => [
        'credentials'   => env('GA4_CREDENTIALS'),
        'property_id'   => env('GA4_PROPERTY_ID'),
        'cache_minutes' => (int) env('GA4_CACHE_MINUTES', 15),
    ],

    // Social login checks the Firebase ID token when the app sends one (firebase_id_token).
    // Set SOCIAL_LOGIN_REQUIRE_TOKEN=true only after every app version sends it; then a login without it is refused.
    'social_login' => [
        'require_firebase_token' => (bool) env('SOCIAL_LOGIN_REQUIRE_TOKEN', false),
    ],

//     'firebase' => [
//     'credentials_file' => storage_path('app/firebase/gig-ctd-app.json'),
// ],

];
