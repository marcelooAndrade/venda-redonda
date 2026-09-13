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

    'receitaws' => [
        // Opcional. Sem token a consulta vai para a API Pública: 3 por
        // minuto por IP, e só CNPJ que já está no banco da ReceitaWS. Com
        // token vai para a API Comercial, que consulta a Receita Federal.
        'token' => env('RECEITAWS_TOKEN'),
        // Só na API Comercial: idade máxima, em dias, do dado que a ReceitaWS
        // pode devolver do cache dela antes de consultar a Receita de novo.
        'dias' => (int) env('RECEITAWS_DIAS', 30),
    ],

];
