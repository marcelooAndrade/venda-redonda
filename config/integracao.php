<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin pessoal
    |--------------------------------------------------------------------------
    |
    | Para onde vão os leads de quem se cadastra no produto. Fica vazio em
    | desenvolvimento e em teste: sem URL nem token, nada sai pela rede.
    |
    */
    'admin_pessoal' => [
        'url' => env('ADMIN_PESSOAL_URL'),
        'token' => env('ADMIN_PESSOAL_TOKEN'),
    ],

];
