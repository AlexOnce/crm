<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'meta' => [
        'token' => env('META_WHATSAPP_TOKEN'),
        'phone_number_id' => env('META_PHONE_NUMBER_ID'),
        'waba_id' => env('META_WABA_ID'),
        'verify_token' => env('META_VERIFY_TOKEN', 'crm_airline_verify_token_2026'),
        'app_secret' => env('META_APP_SECRET'),
        'flow_passenger_id' => env('META_FLOW_PASSENGER_ID', env('META_FLOW_PASSENGER_1_ID', '')),
        'flow_passenger_1_id' => env('META_FLOW_PASSENGER_1_ID', env('META_FLOW_PASSENGER_ID', '')),
        'flow_passenger_2_id' => env('META_FLOW_PASSENGER_2_ID', ''),
        'flow_passenger_3_id' => env('META_FLOW_PASSENGER_3_ID', ''),
        'flow_passenger_4_id' => env('META_FLOW_PASSENGER_4_ID', ''),
        'flow_passenger_5_id' => env('META_FLOW_PASSENGER_5_ID', ''),
        'flow_passenger_group_id' => env('META_FLOW_PASSENGER_GROUP_ID', ''),
        'flow_search_id' => env('META_FLOW_SEARCH_ID', ''),
    ],

    'airclick' => [
        'base_url' => env('AIRCLICK_BASE_URL', 'https://agair.transclicksolutions.com'),
        'client_id' => env('AIRCLICK_CLIENT_ID', 'ext_tcs_airclick'),
        'auth_basic' => env('AIRCLICK_AUTH_BASIC', 'ZXh0X3Rjc19haXJjbGljazozeHRfdGNzXzQxcmNsMWNr'),
        'grant_type' => env('AIRCLICK_GRANT_TYPE', 'client_credentials'),
    ],

    'airline' => [
        'itinerario_image_url' => env('AIRLINE_ITINERARIO_IMAGE_URL', ''),
        'itinerario_web_url' => env('AIRLINE_ITINERARIO_WEB_URL', 'https://tamep.bo/itinerarios'),
    ],

    'goip' => [
        'host' => env('GOIP_HOST', '192.168.1.100'),
        'port' => env('GOIP_PORT', 80),
        'user' => env('GOIP_USER', 'admin'),
        'password' => env('GOIP_PASSWORD', 'admin'),
        'line' => (int) env('GOIP_LINE', 1),
        'lines' => array_map('intval', explode(',', (string) env('GOIP_LINES', '1,2'))),
        'delay' => (int) env('GOIP_DELAY_SECONDS', 15),
        'timeout' => (int) env('GOIP_TIMEOUT_SECONDS', 20),
    ],

    'issabel' => [
        'host' => env('ISSABEL_HOST', '192.168.1.50'),
        'port' => (int) env('ISSABEL_AMI_PORT', 5038),
        'user' => env('ISSABEL_AMI_USER', 'crm_admin'),
        'secret' => env('ISSABEL_AMI_SECRET', ''),
        'source_extension' => env('ISSABEL_SOURCE_EXTENSION', '1'),
        'sip_driver' => env('ISSABEL_SIP_DRIVER', 'sip'),
        'context' => env('ISSABEL_CONTEXT', 'from-internal'),
        'timeout' => (int) env('ISSABEL_TIMEOUT_SECONDS', 10),
        'media_base_url' => env('ISSABEL_MEDIA_BASE_URL', 'https://callcenter.tamep.bo/upload'),
        'hft_url' => env('ISSABEL_HFT_URL', 'https://callcenter.tamep.bo/hft.php'),
    ],

];
