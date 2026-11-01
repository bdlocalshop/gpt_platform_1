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

    'proxycheck' => [
        'key' => env('PROXYCHECK_API_KEY'),
    ],

    'maxmind' => [
        'license_key' => env('MAXMIND_LICENSE_KEY'),
        'db_path' => env('MAXMIND_DB_PATH', storage_path('app/geoip')),
    ],

    'offerwalls' => [
        'bitlabs' => [
            'app_token' => env('BITLABS_APP_TOKEN', 'mock_bitlabs_token'),
            'secret' => env('BITLABS_SECRET', 'test_secret_bitlabs'),
            'iframe_url' => env('BITLABS_IFRAME_URL', 'https://web.bitlabs.ai'),
            'ip_whitelist' => env('BITLABS_IP_WHITELIST', '54.216.0.0/16,52.214.0.0/16,127.0.0.1,::1'),
        ],
        'cpx' => [
            'app_id' => env('CPX_APP_ID', 'mock_cpx_id'),
            'secure_hash' => env('CPX_SECURE_HASH', 'test_secret_cpx'),
            'iframe_url' => env('CPX_IFRAME_URL', 'https://offers.cpx-research.com/index.php'),
            'ip_whitelist' => env('CPX_IP_WHITELIST', '168.119.0.0/16,159.69.0.0/16,127.0.0.1,::1'),
        ],
        'offerwall_gg' => [
            'api_key' => env('OFFERWALL_GG_API_KEY', 'mock_owgg_key'),
            'secret' => env('OFFERWALL_GG_SECRET', 'test_secret_owgg'),
            'iframe_url' => env('OFFERWALL_GG_IFRAME_URL', 'https://offerwall.gg/wall'),
            'ip_whitelist' => env('OFFERWALL_GG_IP_WHITELIST', '104.21.0.0/16,127.0.0.1,::1'),
        ],
        'offerwall_com' => [
            'publisher_id' => env('OFFERWALL_COM_PUB_ID', 'mock_owcom_id'),
            'secret' => env('OFFERWALL_COM_SECRET', 'test_secret_owcom'),
            'iframe_url' => env('OFFERWALL_COM_IFRAME_URL', 'https://wall.offerwall.com'),
            'ip_whitelist' => env('OFFERWALL_COM_IP_WHITELIST', '198.51.100.0/24,127.0.0.1,::1'),
        ],
    ],

];
